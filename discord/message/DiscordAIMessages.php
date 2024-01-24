<?php

use Discord\Builders\MessageBuilder;
use Discord\Parts\Channel\Message;
use Discord\Parts\Thread\Thread;
use Discord\Parts\WebSockets\MessageReaction;

class DiscordAIMessages
{
    private DiscordPlan $plan;
    public ?array $model;
    private array $messageCounter, $messageReplies, $messageFeedback;

    private const REACTION_COMPONENT_NAME = "general-feedback";

    public function __construct(DiscordPlan $plan)
    {
        $this->plan = $plan;
        $this->messageCounter = array();
        $this->messageReplies = array();
        $this->messageFeedback = array();
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

    public function getChatAI(?int $channelID = null): mixed
    {
        return $this->getModel($channelID)?->chatAI;
    }

    public function textAssistance(Message $originalMessage): bool
    {
        global $logger;
        $messageContent = $originalMessage->content;
        $member = $originalMessage->member;
        $object = $this->plan->instructions->getObject(
            $originalMessage->guild,
            $originalMessage->channel,
            $originalMessage->thread,
            $member,
            $originalMessage
        );
        $command = $this->plan->commands->process(
            $originalMessage,
            $member
        );

        if ($command !== null) {
            if ($command instanceof MessageBuilder) {
                $originalMessage->reply($command);
            } else {
                $originalMessage->reply(MessageBuilder::new()->setContent(
                    $this->plan->instructions->replace(array($command), $object)[0]
                ));
            }
            return true;
        } else if ($this->plan->userTickets->track($originalMessage)
            || $this->plan->userTargets->track($originalMessage)
            || $this->plan->userQuestionnaire->track($originalMessage, $object)) {
            return true;
        } else {
            $mute = $this->plan->bot->mute->isMuted($member, $originalMessage->channel, DiscordMute::TEXT);

            if ($mute !== null) {
                $originalMessage->delete();
                $this->plan->utilities->sendMessageInPieces(
                    $member,
                    $this->plan->instructions->replace(array($mute->creation_reason), $object)[0]
                );
            } else if ($this->plan->countingChannels->track($originalMessage)) {
                return true;
            } else {
                $this->plan->messageNotifications->executeMessage($originalMessage);
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

                                        if (!empty($originalMessage->mentions->first())) {
                                            foreach ($originalMessage->mentions as $userObj) {
                                                if ($userObj->id == $this->plan->bot->botID) {
                                                    $mention = true;
                                                    break;
                                                }
                                            }

                                            if (!$mention && !empty($model->mentions)) {
                                                foreach ($model->mentions as $alternativeMention) {
                                                    foreach ($originalMessage->mentions as $userObj) {
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
                                    $limits = $this->isLimited($model, $originalMessage);

                                    if (!empty($limits)) {
                                        foreach ($limits as $limit) {
                                            if ($limit->message !== null) {
                                                $originalMessage->reply(MessageBuilder::new()->setContent(
                                                    $this->plan->instructions->replace(array($limit->message), $object)[0]
                                                ));
                                                break;
                                            }
                                        }
                                    } else {
                                        $cacheKey = array(__METHOD__, $this->plan->planID, $member->id, $messageContent);
                                        $cache = get_key_value_pair($cacheKey);

                                        if ($cache !== null) {
                                            $this->plan->utilities->replyMessageInPieces($originalMessage, $cache);
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
                                                    $originalMessage->reply(MessageBuilder::new()->setContent(
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
                                            $originalMessage->reply(MessageBuilder::new()->setContent(
                                                $promptMessage
                                            ))->done(function (Message $message)
                                            use ($object, $model, $cacheKey, $channel, $originalMessage) {
                                                $reply = $this->rawTextAssistance(
                                                    $originalMessage,
                                                    $message,
                                                    $this->plan->instructions->build($object, $channel->instructions ?? $model->instructions),
                                                    null,
                                                    $channel->debug != null
                                                );

                                                if ($reply === null) {
                                                    if ($channel->failure_message !== null) {
                                                        $this->plan->utilities->editMessage(
                                                            $message,
                                                            $this->plan->instructions->replace(array($channel->failure_message), $object)[0]
                                                        );
                                                    } else if ($channel->debug === null) {
                                                        $this->plan->utilities->deleteMessage($message);
                                                    }
                                                } else {
                                                    set_key_value_pair($cacheKey, $reply);
                                                    $this->plan->component->addReactions($message, self::REACTION_COMPONENT_NAME);
                                                    $this->plan->utilities->replyMessageInPieces($message, $reply);
                                                    $this->messageReplies[$message->id] = $message;
                                                    $this->messageFeedback[$message->id] = array();
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
                                $originalMessage->reply(MessageBuilder::new()->setContent(
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

    public function rawTextAssistance(Message|array $source,
                                      ?Message      $self,
                                      array         $systemInstructions,
                                      int           $extraHash = null,
                                      bool          $debug = false): ?string
    {
        $hasSelf = $self !== null;

        if (is_array($source)) {
            $hasMessage = false;
            $debug = false;
            $channel = array_shift($source);
            $user = array_shift($source);
            $content = array_shift($source);
        } else {
            $hasMessage = true;
            $debug &= $hasSelf;
            $channel = $self->channel;
            $user = $self->member;
            $content = $source->content;
        }
        $parent = $this->plan->utilities->getChannel($channel);
        $chatAI = $this->getChatAI($parent->id);

        if ($chatAI !== null) {
            $hash = overflow_long(overflow_long($this->plan->planID * 31) + (int)$user->id);

            if ($extraHash !== null) {
                $hash = overflow_long(overflow_long($hash * 31) + $extraHash);
            }
            $outcome = $chatAI->getResult(
                $hash,
                array(
                    "messages" => array(
                        array(
                            "role" => "system",
                            "content" => $systemInstructions[0]
                        ),
                        array(
                            "role" => "user",
                            "content" => $content
                        )
                    )
                )
            );

            if ($debug) {
                foreach ($systemInstructions as $instruction) {
                    if (!empty($instruction)) {
                        foreach (str_split($instruction, DiscordInheritedLimits::MESSAGE_MAX_LENGTH) as $split) {
                            $this->plan->utilities->replyMessage(
                                $self,
                                MessageBuilder::new()->setContent($split)
                            );
                        }
                    }
                }
            }
            if (array_shift($outcome)) { // Success
                if ($debug) {
                    foreach ($outcome as $part) {
                        if (@json_decode($part) === null) {
                            $part = json_encode($part);
                        }
                        foreach (str_split($part, DiscordInheritedLimits::MESSAGE_MAX_LENGTH) as $split) {
                            $this->plan->utilities->replyMessage(
                                $self,
                                MessageBuilder::new()->setContent($split)
                            );
                        }
                    }
                }
                $model = array_shift($outcome);
                $reply = array_shift($outcome);
                $content = $chatAI->getText($model, $reply);

                if (!empty($content)) {
                    if ($content == DiscordProperties::NO_REPLY) {
                        return null;
                    } else {
                        $content .= DiscordProperties::NEW_LINE . DiscordSyntax::SPOILER . $systemInstructions[1] . DiscordSyntax::SPOILER;

                        if ($hasMessage) {
                            $reference = $source->message_reference;

                            if ($reference instanceof Message) {
                                $content .= DiscordProperties::NEW_LINE
                                    . DiscordProperties::NEW_LINE
                                    . "Referenced Message by '" . $reference->author->username . "':"
                                    . DiscordProperties::NEW_LINE
                                    . $reference->content;
                            }
                        }
                        $cost = $chatAI->getCost($model, $reply);
                        $currency = new DiscordCurrency($model->currency->code);
                        $thread = $channel instanceof Thread ? $channel->id : null;
                        $date = get_current_date();

                        sql_insert(
                            BotDatabaseTable::BOT_AI_MESSAGES,
                            array(
                                "plan_id" => $this->plan->planID,
                                "bot_id" => $this->plan->bot->botID,
                                "server_id" => $channel->guild_id,
                                "channel_id" => $parent->id,
                                "thread_id" => $thread,
                                "user_id" => $user->id,
                                "message_id" => $hasSelf ? $self->id : null,
                                "message_content" => $content,
                                "creation_date" => $date,
                            )
                        );
                        sql_insert(
                            BotDatabaseTable::BOT_AI_REPLIES,
                            array(
                                "plan_id" => $this->plan->planID,
                                "bot_id" => $this->plan->bot->botID,
                                "server_id" => $channel->guild_id,
                                "channel_id" => $parent->id,
                                "thread_id" => $thread,
                                "user_id" => $user->id,
                                "message_id" => $hasSelf ? $self->id : null,
                                "message_content" => $content,
                                "cost" => $cost,
                                "currency_id" => $currency->exists ? $currency->id : null,
                                "creation_date" => $date,
                            )
                        );
                        return $content;
                    }
                } else {
                    global $logger;
                    $logger->logError(
                        $this->plan->planID,
                        "Failed to get length on text from chat-model for channel/thread with ID: " . $channel->id
                        . "\n" . json_encode($model)
                        . "\n" . json_encode($reply)
                    );
                }
            } else {
                global $logger;
                $logger->logError(
                    $this->plan->planID,
                    "Failed to get text from chat-model for channel/thread with ID: " . $channel->id
                    . "\n" . json_encode($outcome)
                );
            }
        } else {
            global $logger;
            $logger->logError(
                $this->plan->planID,
                "Failed to find an existent chat-model for channel/thread with ID: " . $channel->id
            );
        }
        return null;
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

    private function isLimited(object $model, Message $message): array
    {
        $array = array();
        $serverID = $message->guild_id;
        $channelID = $this->plan->utilities->getChannel($message->channel)->id;
        $threadID = $message->thread?->id;
        $userID = $message->member->id;

        if (!empty($model->messageLimits)
            && !$this->plan->permissions->hasPermission($message->member, "discord.ai.message.limit.ignore")) {
            foreach ($model->messageLimits as $limit) {
                if (($limit->server_id === null || $limit->server_id === $serverID)
                    && ($limit->channel_id === null || $limit->channel_id === $channelID)
                    && ($limit->thread_id === null || $limit->thread_id === $threadID)
                    && ($limit->role_id === null || $this->plan->permissions->hasRole($message->member, $limit->role_id))) {
                    $count = $this->getMessageCount(
                        $limit->server_id,
                        $limit->channel_id,
                        $limit->user !== null ? $userID : null,
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
        if (!empty($model->costLimits)
            && !$this->plan->permissions->hasPermission($message->member, "discord.ai.cost.limit.ignore")) {
            foreach ($model->costLimits as $limit) {
                if (($limit->server_id === null || $limit->server_id === $serverID)
                    && ($limit->channel_id === null || $limit->channel_id === $channelID)
                    && ($limit->thread_id === null || $limit->thread_id === $threadID)
                    && ($limit->role_id === null || $this->plan->permissions->hasRole($message->member, $limit->role_id))
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

    public function sendFeedback(MessageReaction $reaction, int $value): void
    {
        $message = $this->messageReplies[$reaction->message_id] ?? null;

        if ($message !== null
            && !empty($message->mentions->first())
            && !in_array($reaction->member->id, $this->messageFeedback[$message->id])) {
            $channel = $this->plan->utilities->getChannel($message->channel);

            if (!empty(get_sql_query(
                BotDatabaseTable::BOT_AI_FEEDBACK,
                null,
                array(
                    array("server_id", $message->guild_id),
                    array("channel_id", $channel->id),
                    array("thread_id", $message->thread?->id),
                    array("message_id", $message->id),
                    array("user_id", $reaction->member->id),
                    array("deletion_date", null),
                ),
                null,
                1
            ))) {
                return;
            }
            $found = false;
            $date = get_current_date();

            foreach ($message->mentions as $mention) {
                if ($reaction->member->id == $mention->id) {
                    $found = true;
                    $this->messageFeedback[$message->id][] = $reaction->member->id;
                    sql_insert(
                        BotDatabaseTable::BOT_AI_FEEDBACK,
                        array(
                            "plan_id" => $this->plan->planID,
                            "server_id" => $message->guild_id,
                            "channel_id" => $channel->id,
                            "thread_id" => $message->thread?->id,
                            "user_id" => $reaction->member->id,
                            "assisted_user" => 1,
                            "message_id" => $message->id,
                            "value" => $value,
                            "creation_date" => $date,
                        )
                    );
                }
            }

            if (!$found) {
                $this->messageFeedback[$message->id][] = $reaction->member->id;
                sql_insert(
                    BotDatabaseTable::BOT_AI_FEEDBACK,
                    array(
                        "plan_id" => $this->plan->planID,
                        "server_id" => $message->guild_id,
                        "channel_id" => $channel->id,
                        "thread_id" => $message->thread?->id,
                        "user_id" => $reaction->member->id,
                        "message_id" => $message->id,
                        "value" => $value,
                        "creation_date" => $date,
                    )
                );
            }
        }
    }
}