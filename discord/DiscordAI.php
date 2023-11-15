<?php

use Discord\Builders\MessageBuilder;
use Discord\Parts\Channel\Message;
use Discord\Parts\User\Member;
use Discord\Parts\User\User;

class DiscordAI
{
    private DiscordPlan $plan;
    public ?ChatAI $chatAI;

    public function __construct(DiscordPlan $plan)
    {
        $this->plan = $plan;
        $query = get_sql_query(
            BotDatabaseTable::BOT_CHAT_MODEL,
            null,
            array(
                array("plan_id", $this->plan->planID),
                array("deletion_date", null)
            ),
            null,
            1
        );

        if (!empty($query)) {
            $query = $query[0];
            $apiKey = $query->api_key !== null ? array($query->api_key) :
                get_keys_from_file("/root/discord_bot/private/credentials/openai_api_key");

            if ($apiKey === null) {
                global $logger;
                $this->chatAI = null;
                $logger->logError($this->plan->planID, "Failed to find API key for plan: " . $this->plan->planID);
            } else {
                $this->chatAI = new ChatAI(
                    $query->model_family,
                    $apiKey[0],
                    DiscordInheritedLimits::MESSAGE_MAX_LENGTH,
                    $query->temperature,
                    $query->frequency_penalty,
                    $query->presence_penalty,
                    $query->completions,
                    $query->top_p,
                );
            }
        } else {
            $this->chatAI = null;
        }
    }

    public function textAssistance(Message         $message,
                                   User            $user,
                                   Member          $member,
                                   string          $serverName,
                                   string          $channelName,
                                   int|string|null $threadID, string|null $threadName,
                                   string          $messageContent): bool
    {
        $this->plan->bot->processing++;
        global $logger;
        $punishment = $this->plan->moderation->hasPunishment(DiscordPunishment::AI_BLACKLIST, $user->id);
        $object = $this->plan->instructions->getObject(
            $message->guild_id,
            $serverName,
            $message->channel_id,
            $channelName,
            $threadID,
            $threadName,
            $user->id,
            $user->username,
            $user->displayname,
            $messageContent,
            $message->id
        );
        $command = $this->plan->commands->process(
            $message,
            $member
        );

        if ($command !== null) {
            if ($punishment !== null) {
                if ($punishment->notify !== null) {
                    $message->reply(MessageBuilder::new()->setContent(
                        $this->plan->instructions->replace(array($punishment->creation_reason), $object)[0]
                    ));
                }
            } else if ($command instanceof MessageBuilder) {
                $message->reply($command);
            } else {
                $message->reply(MessageBuilder::new()->setContent(
                    $this->plan->instructions->replace(array($command), $object)[0]
                ));
            }
            $this->plan->bot->processing--;
            return true;
        } else {
            $channel = $this->plan->locations->getChannel($message->guild_id, $message->channel_id, $user->id);

            if ($channel !== null) {
                if ($this->chatAI !== null
                    && $this->chatAI->exists) {
                    if ($punishment !== null) {
                        if ($punishment->notify !== null) {
                            $message->reply(MessageBuilder::new()->setContent(
                                $this->plan->instructions->replace(array($punishment->creation_reason), $object)[0]
                            ));
                        }
                    } else {
                        $cooldownKey = array(__METHOD__, $this->plan->planID, $user->id);

                        if (get_key_value_pair($cooldownKey) === null) {
                            set_key_value_pair($cooldownKey, true);
                            if ($user->id != $this->plan->botID) {
                                if ($channel->require_mention) {
                                    $mention = false;

                                    if (!empty($message->mentions->getIterator())) {
                                        foreach ($message->mentions as $userObj) {
                                            if ($userObj->id == $this->plan->botID) {
                                                $mention = true;
                                                break;
                                            }
                                        }

                                        if ($mention) {
                                            $messageContent = str_replace("<@" . $this->plan->botID . ">", "", $messageContent);
                                        } else if (!empty($this->plan->locations->mentions)) {
                                            foreach ($this->plan->locations->mentions as $alternativeMention) {
                                                foreach ($message->mentions as $userObj) {
                                                    if ($userObj->id == $alternativeMention->user_id) {
                                                        $mention = true;
                                                        $messageContent = str_replace("<@" . $alternativeMention->user_id . ">", "", $messageContent);
                                                        break 2;
                                                    }
                                                }
                                            }
                                        }
                                    }
                                } else {
                                    $mention = true;
                                }
                            } else {
                                $mention = false;
                            }

                            if ($mention) {
                                $limits = $this->plan->limits->isLimited($message->guild_id, $message->channel_id, $user->id);

                                if (!empty($limits)) {
                                    foreach ($limits as $limit) {
                                        if ($limit->message !== null) {
                                            $message->reply(MessageBuilder::new()->setContent(
                                                $this->plan->instructions->replace(array($limit->message), $object)[0]
                                            ));
                                            break;
                                        }
                                    }
                                } else {
                                    $cacheKey = array(__METHOD__, $this->plan->planID, $user->id, $messageContent);
                                    $cache = get_key_value_pair($cacheKey);

                                    if ($cache !== null) {
                                        $message->reply(MessageBuilder::new()->setContent($cache));
                                    } else {
                                        if ($channel->require_starting_text !== null
                                            && !starts_with($messageContent, $channel->require_starting_text)
                                            || $channel->require_contained_text !== null
                                            && !str_contains($messageContent, $channel->require_contained_text)
                                            || $channel->require_ending_text !== null
                                            && !ends_with($messageContent, $channel->require_ending_text)
                                            || $channel->min_message_length !== null
                                            && strlen($messageContent) < $channel->min_message_length
                                            || $channel->max_message_length !== null
                                            && strlen($messageContent) > $channel->max_message_length) {
                                            if ($channel->failure_message !== null) {
                                                $message->reply(MessageBuilder::new()->setContent(
                                                    $this->plan->instructions->replace(array($channel->failure_message), $object)[0]
                                                ));
                                            }
                                            $this->plan->bot->processing--;
                                            return true;
                                        }
                                        if (!empty($this->plan->locations->keywords)) {
                                            $result = false;

                                            foreach ($this->plan->locations->keywords as $keyword) {
                                                if ($keyword->keyword !== null) {
                                                    if (str_contains($messageContent, $keyword->keyword)) {
                                                        $result = true;
                                                        break;
                                                    }
                                                }
                                            }

                                            if (!$result) {
                                                if ($channel->failure_message !== null) {
                                                    $message->reply(MessageBuilder::new()->setContent(
                                                        $this->plan->instructions->replace(array($channel->failure_message), $object)[0]
                                                    ));
                                                }
                                                $this->plan->bot->processing--;
                                                return true;
                                            }
                                        }
                                        if ($channel->prompt_message !== null) {
                                            $promptMessage = $this->plan->instructions->replace(array($channel->prompt_message), $object)[0];
                                        } else {
                                            $promptMessage = DiscordProperties::DEFAULT_PROMPT_MESSAGE;
                                        }
                                        $message->reply(MessageBuilder::new()->setContent(
                                            $promptMessage
                                        ))->done(function (Message $message)
                                        use (
                                            $object, $messageContent, $user,
                                            $threadID, $cacheKey, $logger, $channel
                                        ) {
                                            $instructions = $this->plan->instructions->build($object);
                                            $parameters = array(
                                                "messages" => array(
                                                    array(
                                                        "role" => "system",
                                                        "content" => $instructions
                                                    ),
                                                    array(
                                                        "role" => "user",
                                                        "content" => $messageContent
                                                    )
                                                )
                                            );
                                            $reply = $this->chatAI->getResult(
                                                overflow_long(overflow_long($this->plan->planID * 31) + (int)($user->id)),
                                                $parameters
                                            );
                                            $modelReply = $reply[2];

                                            if ($channel->debug !== null) {
                                                foreach (array($parameters, $modelReply) as $debug) {
                                                    foreach (str_split(json_encode($debug), DiscordInheritedLimits::MESSAGE_MAX_LENGTH) as $split) {
                                                        $message->reply(MessageBuilder::new()->setContent(
                                                            str_replace("\\n", DiscordProperties::NEW_LINE, $split)
                                                        ));
                                                    }
                                                }
                                            }
                                            if ($reply[0]) {
                                                $model = $reply[1];
                                                $assistance = $this->chatAI->getText($model, $modelReply);

                                                if ($assistance !== null) {
                                                    $this->plan->conversation->addMessage(
                                                        $message->guild_id,
                                                        $message->channel_id,
                                                        $threadID,
                                                        $user->id,
                                                        $message->id,
                                                        $messageContent,
                                                    );
                                                    $this->plan->conversation->addReply(
                                                        $message->guild_id,
                                                        $message->channel_id,
                                                        $threadID,
                                                        $user->id,
                                                        $message->id,
                                                        $assistance,
                                                        ($modelReply->usage->prompt_tokens * $model->sent_token_cost) + ($modelReply->usage->completion_tokens * $model->received_token_cost),
                                                        $model->currency->code
                                                    );
                                                    set_key_value_pair($cacheKey, $assistance, $channel->message_retention);
                                                } else {
                                                    $logger->logError($this->plan->planID, "Failed to get text from chat-model for plan: " . $this->planID);
                                                }
                                            } else {
                                                $assistance = null;
                                                $logger->logError($this->plan->planID, $modelReply);
                                            }

                                            if ($assistance === null || $assistance == DiscordProperties::NO_REPLY) {
                                                if ($channel->failure_message !== null) {
                                                    $message->edit(MessageBuilder::new()->setContent(
                                                        $this->plan->instructions->replace(array($channel->failure_message), $object)[0]
                                                    ));
                                                } else {
                                                    $message->delete();
                                                }
                                            } else {
                                                $pieces = str_split($assistance, DiscordInheritedLimits::MESSAGE_MAX_LENGTH);
                                                $message->edit(MessageBuilder::new()->setContent(
                                                    array_shift($pieces)
                                                ));

                                                if (!empty($pieces)) {
                                                    foreach (str_split($assistance, DiscordInheritedLimits::MESSAGE_MAX_LENGTH) as $split) {
                                                        $message->reply(MessageBuilder::new()->setContent($split));
                                                    }
                                                }
                                            }
                                        });
                                    }
                                }
                            }
                            if ($channel->message_cooldown !== null) {
                                set_key_value_pair($cooldownKey, true, $channel->message_cooldown);
                            } else {
                                clear_memory(array($cooldownKey));
                            }
                        } else if ($channel->cooldown_message !== null
                            && $channel->message_cooldown !== null) {
                            $message->reply(MessageBuilder::new()->setContent(
                                $this->plan->instructions->replace(array($channel->cooldown_message), $object)[0]
                            ));
                        }
                    }
                } else {
                    $logger->logError($this->plan->planID, "Failed to find chat-model for plan: " . $this->plan->planID);
                }
                $this->plan->bot->processing--;
                return true;
            }
        }
        $this->plan->bot->processing--;
        return false;
    }
}