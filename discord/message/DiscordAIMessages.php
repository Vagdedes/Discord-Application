<?php

use Discord\Builders\MessageBuilder;
use Discord\Parts\Channel\Channel;
use Discord\Parts\Channel\Message;
use Discord\Parts\Thread\Thread;
use Discord\Parts\User\Member;
use Discord\Parts\User\User;

class DiscordAIMessages
{
    private DiscordPlan $plan;
    public ?array $model;
    private array $messageCounter;

    //todo image ai

    public function __construct(DiscordPlan $plan)
    {
        $this->plan = $plan;
        $this->messageCounter = array();
        $query = get_sql_query(
            BotDatabaseTable::BOT_AI_CHAT_MODEL,
            null,
            array(
                array("plan_id", $this->plan->planID),
                array("deletion_date", null)
            )
        );

        if (!empty($query)) {
            foreach ($query as $row) {
                $apiKey = $row->api_key !== null ? array($row->api_key) :
                    get_keys_from_file("/root/discord_bot/private/credentials/openai_api_key");

                if ($apiKey === null) {
                    global $logger;
                    $logger->logError($this->plan->planID, "Failed to find API key for plan: " . $this->plan->planID);
                } else {
                    $object = new stdClass();
                    $object->chatAI = new ChatAI(
                        $row->model_family,
                        $apiKey[0],
                        DiscordInheritedLimits::MESSAGE_MAX_LENGTH,
                        $row->temperature,
                        $row->frequency_penalty,
                        $row->presence_penalty,
                        $row->completions,
                        $row->top_p,
                    );
                    $object->instructions = array();
                    $object->mentions = get_sql_query(
                        BotDatabaseTable::BOT_AI_MENTIONS,
                        null,
                        array(
                            array("deletion_date", null),
                            null,
                            array("ai_model_id", "IS", null, 0),
                            array("ai_model_id", $row->id),
                            null,
                            null,
                            array("expiration_date", "IS", null, 0),
                            array("expiration_date", ">", get_current_date()),
                            null
                        )
                    );
                    $object->keywords = get_sql_query(
                        BotDatabaseTable::BOT_AI_KEYWORDS,
                        null,
                        array(
                            array("deletion_date", null),
                            null,
                            array("ai_model_id", "IS", null, 0),
                            array("ai_model_id", $row->id),
                            null,
                            null,
                            array("expiration_date", "IS", null, 0),
                            array("expiration_date", ">", get_current_date()),
                            null
                        )
                    );
                    $object->messageLimits = get_sql_query(
                        BotDatabaseTable::BOT_AI_MESSAGE_LIMITS,
                        null,
                        array(
                            array("deletion_date", null),
                            null,
                            array("ai_model_id", "IS", null, 0),
                            array("ai_model_id", $row->id),
                            null,
                            null,
                            array("expiration_date", "IS", null, 0),
                            array("expiration_date", ">", get_current_date()),
                            null
                        )
                    );
                    $object->costLimits = get_sql_query(
                        BotDatabaseTable::BOT_AI_COST_LIMITS,
                        null,
                        array(
                            array("deletion_date", null),
                            null,
                            array("ai_model_id", "IS", null, 0),
                            array("ai_model_id", $row->id),
                            null,
                            null,
                            array("expiration_date", "IS", null, 0),
                            array("expiration_date", ">", get_current_date()),
                            null
                        )
                    );
                    $childQuery = get_sql_query(
                        BotDatabaseTable::BOT_AI_INSTRUCTIONS,
                        array("instruction_id"),
                        array(
                            array("deletion_date", null),
                            null,
                            array("ai_model_id", "IS", null, 0),
                            array("ai_model_id", $row->id),
                            null,
                            null,
                            array("expiration_date", "IS", null, 0),
                            array("expiration_date", ">", get_current_date()),
                            null
                        )
                    );

                    if (!empty($childQuery)) {
                        foreach ($childQuery as $arrayChildKey => $childRow) {
                            $object->instructions[$arrayChildKey] = $childRow->instruction_id;
                        }
                    }
                    $this->model[$row->channel_id ?? 0] = $object;
                }
            }
        } else {
            $this->model = array();
        }
    }

    public function getModel(?int $channelID = null): ?object
    {
        return $channelID !== null
            ? (array_key_exists($channelID, $this->model) ? $this->model[$channelID] : $this?->model[0])
            : $this?->model[0];
    }

    public function getChatAI(?int $channelID = null): ?ChatAI
    {
        return $this->getModel($channelID)?->chatAI;
    }

    public function textAssistance(Message $message,
                                   Member  $member,
                                   string  $messageContent): bool
    {
        global $logger;
        $channelObj = $this->plan->utilities->getChannel($message->channel);
        $object = $this->plan->instructions->getObject(
            $message->guild,
            $channelObj,
            $message->thread,
            $member,
            $message
        );
        $command = $this->plan->commands->process(
            $message,
            $member
        );

        if ($command !== null) {
            if ($command instanceof MessageBuilder) {
                $message->reply($command);
            } else {
                $message->reply(MessageBuilder::new()->setContent(
                    $this->plan->instructions->replace(array($command), $object)[0]
                ));
            }
            return true;
        } else if ($this->plan->userTickets->track($message)
            || $this->plan->userTargets->track($message)
            || $this->plan->userQuestionnaire->track($message, $object)) {
            return true;
        } else {
            $mute = $this->plan->bot->mute->isMuted($member, $message->channel, DiscordMute::TEXT);

            if ($mute !== null) {
                $message->delete();
                $this->plan->utilities->sendMessageInPieces(
                    $member,
                    $this->plan->instructions->replace(array($mute->creation_reason), $object)[0]
                );
            } else if ($this->plan->countingChannels->track($message)) {
                return true;
            } else {
                $this->plan->channelNotifications->executeMessage($message);
                $channel = $object->channel;

                if ($channel !== null) {
                    $model = $this->getModel($channel->channel_id);

                    if ($model !== null) {
                        $chatAI = $model->chatAI;

                        if ($chatAI->exists) {
                            $cooldownKey = array(__METHOD__, $this->plan->planID, $member->id);

                            if (get_key_value_pair($cooldownKey) === null) {
                                set_key_value_pair($cooldownKey, true);
                                if ($member->id != $this->plan->bot->botID) {
                                    if ($channel->require_mention) {
                                        $mention = false;

                                        if (!empty($message->mentions->first())) {
                                            foreach ($message->mentions as $userObj) {
                                                if ($userObj->id == $this->plan->bot->botID) {
                                                    $mention = true;
                                                    break;
                                                }
                                            }

                                            if (!$mention && !empty($model->mentions)) {
                                                foreach ($model->mentions as $alternativeMention) {
                                                    foreach ($message->mentions as $userObj) {
                                                        if ($userObj->id == $alternativeMention->user_id) {
                                                            $mention = true;
                                                            break 2;
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    } else {
                                        $mention = true;
                                    }

                                    if (!$mention && !empty($model->keywords)) {
                                        foreach ($model->keywords as $keyword) {
                                            if ($keyword->keyword !== null) {
                                                if (str_contains($messageContent, $keyword->keyword)) {
                                                    $mention = true;
                                                    break;
                                                }
                                            }
                                        }
                                    }
                                } else {
                                    $mention = false;
                                }

                                if ($mention) {
                                    $limits = $this->isLimited($model, $message->guild_id, $message->channel_id, $member->id);

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
                                        $cacheKey = array(__METHOD__, $this->plan->planID, $member->id, $messageContent);
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
                                                return true;
                                            }
                                            if ($channel->prompt_message !== null) {
                                                $promptMessage = $this->plan->instructions->replace(array($channel->prompt_message), $object)[0];
                                            } else {
                                                $promptMessage = DiscordProperties::DEFAULT_PROMPT_MESSAGE;
                                            }
                                            $threadID = $message->thread?->id;
                                            $message->reply(MessageBuilder::new()->setContent(
                                                $promptMessage
                                            ))->done(function (Message $message)
                                            use (
                                                $object, $messageContent, $member, $chatAI, $model,
                                                $threadID, $cacheKey, $logger, $channel, $channelObj
                                            ) {
                                                $instructions = $this->plan->instructions->build($object, $channel->instructions ?? $model->instructions);
                                                $reference = $message->message_reference?->content ?? null;
                                                $reply = $this->rawTextAssistance(
                                                    $member,
                                                    $channelObj,
                                                    $instructions[0],
                                                    $messageContent
                                                    . ($reference === null
                                                        ? ""
                                                        : DiscordProperties::NEW_LINE
                                                        . DiscordProperties::NEW_LINE
                                                        . "Reference Message:"
                                                        . DiscordProperties::NEW_LINE
                                                        . $reference),
                                                );
                                                $modelReply = $reply[2];

                                                if ($channel->debug !== null) {
                                                    if (!empty($instructions[0])) {
                                                        foreach (str_split($instructions[0], DiscordInheritedLimits::MESSAGE_MAX_LENGTH) as $split) {
                                                            $this->plan->utilities->replyMessage(
                                                                $message,
                                                                MessageBuilder::new()->setContent($split)
                                                            );
                                                        }
                                                    } else {
                                                        $this->plan->utilities->replyMessage(
                                                            $message,
                                                            MessageBuilder::new()->setContent("NO MESSAGE")
                                                        );
                                                    }
                                                    if (!empty($instructions[1])) {
                                                        foreach (str_split($instructions[1], DiscordInheritedLimits::MESSAGE_MAX_LENGTH) as $split) {
                                                            $this->plan->utilities->replyMessage(
                                                                $message,
                                                                MessageBuilder::new()->setContent($split)
                                                            );
                                                        }
                                                    } else {
                                                        $this->plan->utilities->replyMessage(
                                                            $message,
                                                            MessageBuilder::new()->setContent("NO DISCLAIMER")
                                                        );
                                                    }
                                                    foreach (str_split(json_encode($modelReply), DiscordInheritedLimits::MESSAGE_MAX_LENGTH) as $split) {
                                                        $this->plan->utilities->replyMessage(
                                                            $message,
                                                            MessageBuilder::new()->setContent($split)
                                                        );
                                                    }
                                                }
                                                if ($reply[0]) {
                                                    $model = $reply[1];
                                                    $assistance = $chatAI->getText($model, $modelReply);
                                                    $initialAssistance = $assistance;

                                                    if ($assistance !== null) {
                                                        $assistance .= $instructions[1];
                                                        $this->addMessage(
                                                            $message->guild_id,
                                                            $message->channel_id,
                                                            $threadID,
                                                            $member->id,
                                                            $message->id,
                                                            $messageContent,
                                                        );
                                                        $this->addReply(
                                                            $message->guild_id,
                                                            $message->channel_id,
                                                            $threadID,
                                                            $member->id,
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
                                                    $initialAssistance = null;
                                                    $logger->logError($this->plan->planID, $modelReply);
                                                }

                                                if ($assistance === null || $initialAssistance == DiscordProperties::NO_REPLY) {
                                                    if ($channel->failure_message !== null) {
                                                        $this->plan->utilities->editMessage(
                                                            $message,
                                                            $this->plan->instructions->replace(array($channel->failure_message), $object)[0]
                                                        );
                                                    } else if ($channel->debug === null) {
                                                        $this->plan->utilities->deleteMessage($message);
                                                    }
                                                } else {
                                                    $this->plan->utilities->replyMessageInPieces($message, $assistance);
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
                        } else {
                            $logger->logError($this->plan->planID, "Failed to find an existent chat-model for plan: " . $this->plan->planID);
                        }
                    } else {
                        $logger->logError($this->plan->planID, "Failed to find any chat-model for plan: " . $this->plan->planID);
                    }
                    return true;
                }
            }
        }
        return false;
    }

    // 1: Success, 2: Model, 3: Reply
    public function rawTextAssistance(User|Member    $userObject,
                                      Channel|Thread $channel,
                                      string         $instructions, string $user,
                                      ?int           $extraHash = null): array
    {
        $hash = overflow_long(overflow_long($this->plan->planID * 31) + (int)($userObject->id));

        if ($extraHash !== null) {
            $hash = overflow_long(overflow_long($hash * 31) + $extraHash);
        }
        $chatAI = $this->getChatAI($this->plan->utilities->getChannel($channel)->id);

        if ($chatAI === null) {
            $outcome = array(false, null, null);
        } else {
            $outcome = $chatAI->getResult(
                $hash,
                array(
                    "messages" => array(
                        array(
                            "role" => "system",
                            "content" => $instructions
                        ),
                        array(
                            "role" => "user",
                            "content" => $user
                        )
                    )
                )
            );
        }
        return $outcome;
    }

    // Separator

    public function getMessages(int|string|null $serverID, int|string|null $channelID, int|string|null $threadID,
                                int|string      $userID,
                                ?int            $limit = 0, bool $object = true): array
    {
        set_sql_cache("1 second");
        $array = get_sql_query(
            BotDatabaseTable::BOT_AI_MESSAGES,
            array("creation_date", "message_content"),
            array(
                array("user_id", $userID),
                $serverID !== null ? array("server_id", $serverID) : "",
                $channelID !== null ? array("channel_id", $channelID) : "",
                $threadID !== null ? array("thread_id", $threadID) : "",
                array("deletion_date", null),
                array("plan_id", $this->plan->planID),
            ),
            array(
                "DESC",
                "id"
            ),
            $limit
        );

        if (!$object) {
            foreach ($array as $arrayKey => $row) {
                unset($array[$arrayKey]);
                $array[strtotime($row->creation_date)] = $row->message_content;
            }
            krsort($array);
        }
        return $array;
    }

    public function getReplies(int|string|null $serverID, int|string|null $channelID, int|string|null $threadID,
                               int|string      $userID,
                               ?int            $limit = 0, bool $object = true): array
    {
        set_sql_cache("1 second");
        $array = get_sql_query(
            BotDatabaseTable::BOT_AI_REPLIES,
            array("creation_date", "message_content"),
            array(
                array("user_id", $userID),
                $serverID !== null ? array("server_id", $serverID) : "",
                $channelID !== null ? array("channel_id", $channelID) : "",
                $threadID !== null ? array("thread_id", $threadID) : "",
                array("deletion_date", null),
                array("plan_id", $this->plan->planID),
            ),
            array(
                "DESC",
                "id"
            ),
            $limit
        );

        if (!$object) {
            foreach ($array as $arrayKey => $row) {
                unset($array[$arrayKey]);
                $array[strtotime($row->creation_date)] = $row->message_content;
            }
            krsort($array);
        }
        return $array;
    }

    public function getConversation(int|string|null $serverID, int|string|null $channelID, int|string|null $threadID,
                                    int|string      $userID,
                                    ?int            $limit = 0, bool $object = true): array
    {
        $final = array();
        $messages = $this->getMessages($serverID, $channelID, $threadID, $userID, $limit, $object);
        $replies = $this->getReplies($serverID, $channelID, $threadID, $userID, $limit, $object);

        if (!empty($messages)) {
            if ($object) {
                foreach ($messages as $row) {
                    $row->user = true;
                    $final[strtotime($row->creation_date)] = $row;
                }
            } else {
                foreach ($messages as $arrayKey => $row) {
                    $final[$arrayKey] = "user: " . $row;
                }
            }
        }
        if (!empty($replies)) {
            if ($object) {
                foreach ($replies as $row) {
                    $row->user = false;
                    $final[strtotime($row->creation_date)] = $row;
                }
            } else {
                foreach ($messages as $arrayKey => $row) {
                    $final[$arrayKey] = "bot (you): " . $row;
                }
            }
        }
        krsort($final);
        return $final;
    }

    // Separator

    private function getCost(int|string|null $serverID, int|string|null $channelID, int|string|null $userID,
                             int|string      $pastLookup): float
    {
        $cacheKey = array(__METHOD__, $this->plan->planID, $serverID, $channelID, $userID, $pastLookup);
        $cache = get_key_value_pair($cacheKey);

        if ($cache !== null) {
            return $cache;
        } else {
            $cost = 0.0;
            $array = get_sql_query(
                BotDatabaseTable::BOT_AI_REPLIES,
                array("cost"),
                array(
                    $serverID !== null ? array("server_id", $serverID) : "",
                    $channelID !== null ? array("channel_id", $channelID) : "",
                    $userID !== null ? array("user_id", $userID) : "",
                    array("deletion_date", null),
                    array("creation_date", ">", get_past_date($pastLookup)),
                    array("plan_id", $this->plan->planID),
                ),
                array(
                    "DESC",
                    "id"
                )
            );

            foreach ($array as $row) {
                $cost += $row->cost;
            }
            set_key_value_pair($cacheKey, $cost, $pastLookup);
            return $cost;
        }
    }

    private function getMessageCount(int|string|null $serverID, int|string|null $channelID,
                                     int|string|null $userID, int|string $pastLookup): float
    {
        $cacheKey = array(__METHOD__, $this->plan->planID, $serverID, $channelID, $userID, $pastLookup);
        $cache = get_key_value_pair($cacheKey);

        if ($cache !== null) {
            return $cache;
        } else {
            $array = get_sql_query(
                BotDatabaseTable::BOT_AI_REPLIES,
                array("id"),
                array(
                    $serverID !== null ? array("server_id", $serverID) : "",
                    $channelID !== null ? array("channel_id", $channelID) : "",
                    $userID !== null ? array("user_id", $userID) : "",
                    array("deletion_date", null),
                    array("creation_date", ">", get_past_date($pastLookup)),
                    array("plan_id", $this->plan->planID),
                ),
                array(
                    "DESC",
                    "id"
                )
            );
            $amount = sizeof($array);
            set_key_value_pair($cacheKey, $amount, $pastLookup);
            return $amount;
        }
    }

    private function isLimited(object     $model,
                               int|string $serverID,
                               int|string $channelID,
                               int|string $userID): array
    {
        $array = array();

        if (!empty($model->messageLimits)) {
            foreach ($model->messageLimits as $limit) {
                if (($limit->server_id === null || $limit->server_id === $serverID)
                    && ($limit->channel_id === null || $limit->channel_id === $channelID)) {
                    $loopUserID = $limit->user !== null ? $userID : null;
                    $count = $this->getMessageCount(
                        $limit->server_id,
                        $limit->channel_id,
                        $loopUserID,
                        $limit->past_lookup,
                    );
                    $hash = string_to_integer(
                        serialize(get_object_vars($model)) . $serverID . $channelID . $userID,
                        true
                    );

                    if (array_key_exists($hash, $this->messageCounter)) {
                        $this->messageCounter[$hash]++;
                        $count = $this->messageCounter[$hash];
                    } else {
                        $this->messageCounter[$hash] = $count;
                    }

                    if ($count >= $limit->limit) {
                        $array[] = $limit;
                    }
                }
            }
        }
        if (!empty($model->costLimits)) {
            foreach ($model->costLimits as $limit) {
                if (($limit->server_id === null || $limit->server_id === $serverID)
                    && ($limit->channel_id === null || $limit->channel_id === $channelID)
                    && $this->getCost(
                        $limit->server_id,
                        $limit->channel_id,
                        $limit->user !== null ? $userID : null,
                        $limit->past_lookup
                    ) >= $limit->limit) {
                    $array[] = $limit;
                }
            }
        }
        return $array;
    }

    // Separator

    private function addReply(int|string      $serverID, int|string $channelID,
                              int|string|null $threadID,
                              int|string      $userID,
                              int|string      $messageID, string $messageContent,
                              int|float       $cost, string $currencyCode): void
    {
        $currency = new DiscordCurrency($currencyCode);
        sql_insert(
            BotDatabaseTable::BOT_AI_REPLIES,
            array(
                "plan_id" => $this->plan->planID,
                "bot_id" => $this->plan->bot->botID,
                "server_id" => $serverID,
                "channel_id" => $channelID,
                "thread_id" => $threadID,
                "user_id" => $userID,
                "message_id" => $messageID,
                "message_content" => $messageContent,
                "cost" => $cost,
                "currency_id" => $currency->exists ? $currency->id : null,
                "creation_date" => get_current_date(),
            )
        );
    }

    private function addMessage(int|string      $serverID, int|string $channelID,
                                int|string|null $threadID,
                                int|string      $userID,
                                int|string      $messageID, string $messageContent): void
    {
        sql_insert(
            BotDatabaseTable::BOT_AI_MESSAGES,
            array(
                "plan_id" => $this->plan->planID,
                "bot_id" => $this->plan->bot->botID,
                "server_id" => $serverID,
                "channel_id" => $channelID,
                "thread_id" => $threadID,
                "user_id" => $userID,
                "message_id" => $messageID,
                "message_content" => $messageContent,
                "creation_date" => get_current_date(),
            )
        );
    }
}