<?php

use Discord\Builders\MessageBuilder;
use Discord\Parts\Channel\Channel;
use Discord\Parts\Channel\Message;
use Discord\Parts\Guild\Role;
use Discord\Parts\Interactions\Interaction;
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
        $this->model = array();
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
            $array = array();

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
                    $object->localInstructions = get_sql_query(
                        BotDatabaseTable::BOT_AI_INSTRUCTIONS,
                        null,
                        array(
                            array("deletion_date", null),
                            array("public", null),
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
                    if (!empty($object->localInstructions)) {
                        foreach ($object->localInstructions as $childKey => $instruction) {
                            $object->localInstructions[$childKey] = $instruction->instruction_id;
                        }
                    }
                    $object->publicInstructions = get_sql_query(
                        BotDatabaseTable::BOT_AI_INSTRUCTIONS,
                        null,
                        array(
                            array("deletion_date", null),
                            array("public", "IS NOT", null),
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
                    if (!empty($object->publicInstructions)) {
                        foreach ($object->publicInstructions as $childKey => $instruction) {
                            $object->publicInstructions[$childKey] = $instruction->instruction_id;
                        }
                    }
                    $this->model[$row->id] = $object;
                }
            }
        }
    }

    public function getChatAI(int|string $aiModelID): mixed
    {
        $model = $this->model[$aiModelID] ?? null;
        return $model?->chatAI;
    }

    public function textAssistance(Message $originalMessage): bool
    {
        global $logger;
        $messageContent = $originalMessage->content;
        $member = $originalMessage->member;
        $object = $this->plan->instructions->getObject(
            $originalMessage->guild,
            $originalMessage->channel,
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
        } else {
            $mute = $this->plan->bot->mute->isMuted($member, $originalMessage->channel, DiscordMute::TEXT);

            if ($mute !== null) {
                $originalMessage->delete();
                $this->plan->utilities->sendMessageInPieces(
                    $member,
                    $this->plan->instructions->replace(array($mute->creation_reason), $object)[0]
                );
                return true;
            } else {
                $channel = $object->channel;
                $foundChannel = $channel !== null;
                $filter = $foundChannel && $channel->filter !== null
                    ? $this->plan->chatFilteredMessages->run($originalMessage)
                    : null;

                if ($filter !== null) {
                    $originalMessage->delete();

                    if (!empty($filter)) {
                        $this->plan->utilities->sendMessageInPieces(
                            $member,
                            $this->plan->instructions->replace(array($filter), $object)[0]
                        );
                    }
                    return true;
                } else {
                    $stop = $this->plan->userTickets->track($originalMessage)
                        || $this->plan->userTargets->track($originalMessage)
                        || $this->plan->userQuestionnaire->track($originalMessage, $object)
                        || $this->plan->countingChannels->track($originalMessage)
                        || $this->plan->objectiveChannels->trackCreation($originalMessage)
                        || $this->plan->messageNotifications->executeMessage($originalMessage);
                    $this->plan->tranferredMessages->trackCreation($originalMessage);

                    if (!$stop
                        && $foundChannel
                        && $channel->ai_model_id !== null) {
                        $model = $this->model[$channel->ai_model_id] ?? null;

                        if ($model !== null) {
                            $chatAI = $model->chatAI;

                            if ($chatAI->exists) {
                                $cooldownKey = array(__METHOD__, $this->plan->planID, $member->id);

                                if (get_key_value_pair($cooldownKey) === null) {
                                    set_key_value_pair($cooldownKey, true);
                                    if ($member->id != $this->plan->bot->botID) {
                                        if ($channel->require_mention !== null
                                            && ($channel->not_require_mention_time === null
                                                || strtotime(get_past_date($channel->not_require_mention_time)) > $member->joined_at->second)) {
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
                                        } else if ($channel->ignore_mention !== null) {
                                            $mention = true;

                                            if (!empty($originalMessage->mentions->first())) {
                                                foreach ($originalMessage->mentions as $userObj) {
                                                    if ($userObj->id == $this->plan->bot->botID) {
                                                        $mention = false;
                                                        break;
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
                                            return true;
                                        } else {
                                            $cacheKey = array(__METHOD__, $channel->ai_model_id, $member->id, $messageContent);
                                            $cache = get_key_value_pair($cacheKey);

                                            if ($channel->prompt_message !== null) {
                                                $promptMessage = $this->plan->instructions->replace(array($channel->prompt_message), $object)[0];
                                            } else {
                                                $promptMessage = DiscordProperties::DEFAULT_PROMPT_MESSAGE;
                                            }
                                            if ($cache !== null) {
                                                $originalMessage->reply(MessageBuilder::new()->setContent(
                                                    $promptMessage
                                                ))->done(function (Message $message) use ($cache) {
                                                    $this->plan->utilities->replyMessageInPieces($message, $cache);
                                                });
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
                                                    return false;
                                                }
                                                $originalMessage->reply(MessageBuilder::new()->setContent(
                                                    $promptMessage
                                                ))->done(function (Message $message)
                                                use ($object, $model, $cacheKey, $channel, $originalMessage) {
                                                    $reply = $this->rawTextAssistance(
                                                        $channel->ai_model_id,
                                                        $originalMessage,
                                                        $message,
                                                        array(
                                                            $object,
                                                            $channel->local_instructions ?? $model->localInstructions,
                                                            $channel->public_instructions ?? $model->publicInstructions,
                                                        ),
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

                                                        if ($channel->feedback !== null) {
                                                            $this->plan->component->addReactions($message, self::REACTION_COMPONENT_NAME);
                                                        }
                                                        $this->plan->utilities->replyMessageInPieces($message, $reply);

                                                        $hash = $this->plan->utilities->hash(
                                                            $message->guild_id,
                                                            $message->channel_id,
                                                            $message->thread?->id,
                                                            $message->id
                                                        );
                                                        $this->messageReplies[$hash] = $message;
                                                        $this->messageFeedback[$hash] = array();
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
                    }
                }
            }
        }
        return false;
    }

    public function rawTextAssistance(int|string    $aiModelID,
                                      Message|array $source,
                                      ?Message      $self,
                                      array         $systemInstructions,
                                      int           $extraHash = null,
                                      bool          $debug = false): ?string
    {
        if (is_array($source)) {
            $debug = false;
            $channel = array_shift($source);
            $user = array_shift($source);
            $content = array_shift($source);
        } else {
            $debug &= $self !== null;
            $channel = $self->channel;
            $user = $source->member;
            $content = $source->content;
            $reference = $source->message_reference;

            if ($reference instanceof Message) {
                $content .= DiscordProperties::NEW_LINE
                    . DiscordProperties::NEW_LINE
                    . "Referenced Message by '" . $reference->author->username . "':"
                    . DiscordProperties::NEW_LINE
                    . $reference->content;
            }
        }
        $parent = $this->plan->utilities->getChannel($channel);
        $chatAI = $this->getChatAI($aiModelID);

        if ($chatAI !== null) {
            $hash = overflow_long(overflow_long($this->plan->planID * 31) + (int)$user->id);

            if ($extraHash !== null) {
                $hash = overflow_long(overflow_long($hash * 31) + $extraHash);
            }
            $systemInstructions = $this->plan->instructions->build(
                $systemInstructions[0],
                $systemInstructions[1],
                $systemInstructions[2],
                $content
            );
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
                    if (!empty($systemInstructions[1])) {
                        $content .= DiscordProperties::NEW_LINE . DiscordSyntax::SPOILER . $systemInstructions[1] . DiscordSyntax::SPOILER;
                    }
                    $cost = $chatAI->getCost($model, $reply);
                    $thread = $channel instanceof Thread ? $channel->id : null;
                    $date = get_current_date();

                    sql_insert(
                        BotDatabaseTable::BOT_AI_MESSAGES,
                        array(
                            "plan_id" => $this->plan->planID,
                            "ai_hash" => $extraHash,
                            "bot_id" => $this->plan->bot->botID,
                            "server_id" => $channel->guild_id,
                            "channel_id" => $parent->id,
                            "thread_id" => $thread,
                            "user_id" => $user->id,
                            "message_id" => $self?->id,
                            "message_content" => $content,
                            "creation_date" => $date,
                        )
                    );
                    sql_insert(
                        BotDatabaseTable::BOT_AI_REPLIES,
                        array(
                            "plan_id" => $this->plan->planID,
                            "ai_hash" => $extraHash,
                            "bot_id" => $this->plan->bot->botID,
                            "server_id" => $channel->guild_id,
                            "channel_id" => $parent->id,
                            "thread_id" => $thread,
                            "user_id" => $user->id,
                            "message_id" => $self?->id,
                            "message_content" => $content,
                            "cost" => $cost,
                            "currency_id" => $model->currency->id,
                            "creation_date" => $date,
                        )
                    );
                    return $content;
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
                                int|string|null $limit = 0, bool $object = true): array
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
            $limit !== null ? (int)$limit : 0
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
                               int|string|null $limit = 0, bool $object = true): array
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
            $limit !== null ? (int)$limit : 0
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
        $date = get_current_date();

        if (!empty($model->messageLimits)
            && !$this->plan->permissions->hasPermission($message->member, "discord.ai.message.limit.ignore")) {
            foreach ($model->messageLimits as $limit) {
                if (($limit->server_id === null
                        || $limit->server_id === $serverID
                        && ($limit->channel_id === null || $limit->channel_id === $channelID)
                        && ($limit->thread_id === null || $limit->thread_id === $threadID))
                    && ($limit->role_id === null || $this->plan->permissions->hasRole($message->member, $limit->role_id))) {
                    $hasTimeout = $limit->timeout !== null;

                    if ($hasTimeout) {
                        set_sql_cache("1 minute");
                        $timeout = !empty(get_sql_query(
                            BotDatabaseTable::BOT_AI_MESSAGE_TIMEOUTS,
                            array("id"),
                            array(
                                array("limit_id", $limit->id),
                                array("user_id", $userID),
                                array("deletion_date", null),
                                null,
                                array("expiration_date", "IS", null, 0),
                                array("expiration_date", ">", $date),
                                null
                            ),
                            null,
                            1
                        ));
                    } else {
                        $timeout = false;
                    }

                    if ($timeout) {
                        $array[] = $limit;
                    } else {
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

                            if ($hasTimeout) {
                                sql_insert(
                                    BotDatabaseTable::BOT_AI_MESSAGE_TIMEOUTS,
                                    array(
                                        "limit_id" => $limit->id,
                                        "user_id" => $userID,
                                        "creation_date" => $date,
                                        "expiration_date" => get_future_date($limit->past_lookup),
                                    )
                                );
                            }
                        }
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
                    && ($limit->role_id === null || $this->plan->permissions->hasRole($message->member, $limit->role_id))) {
                    $hasTimeout = $limit->timeout !== null;

                    if ($hasTimeout) {
                        set_sql_cache("1 minute");
                        $timeout = !empty(get_sql_query(
                            BotDatabaseTable::BOT_AI_COST_TIMEOUTS,
                            array("id"),
                            array(
                                array("limit_id", $limit->id),
                                array("user_id", $userID),
                                array("deletion_date", null),
                                null,
                                array("expiration_date", "IS", null, 0),
                                array("expiration_date", ">", $date),
                                null
                            ),
                            null,
                            1
                        ));
                    } else {
                        $timeout = false;
                    }

                    if ($timeout) {
                        $array[] = $limit;
                    } else if ($this->getCost(
                            $limit->server_id,
                            $limit->channel_id,
                            $limit->user !== null ? $userID : null,
                            $limit->past_lookup
                        ) >= $limit->limit) {
                        $array[] = $limit;

                        if ($hasTimeout) {
                            sql_insert(
                                BotDatabaseTable::BOT_AI_COST_TIMEOUTS,
                                array(
                                    "limit_id" => $limit->id,
                                    "user_id" => $userID,
                                    "creation_date" => $date,
                                    "expiration_date" => get_future_date($limit->past_lookup),
                                )
                            );
                        }
                    }
                }
            }
        }
        return $array;
    }

    // Separator

    public function sendFeedback(MessageReaction $reaction, ?int $value): void
    {
        $hash = $this->plan->utilities->hash(
            $reaction->guild_id,
            $reaction->channel_id,
            $reaction->message->thread?->id,
            $reaction->message_id
        );
        $message = $this->messageReplies[$hash] ?? null;

        if ($message !== null
            && !empty($message->mentions->first())
            && !in_array($reaction->member->id, $this->messageFeedback[$hash])) {
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
                    $this->messageFeedback[$hash][] = $reaction->member->id;
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
                $this->messageFeedback[$hash][] = $reaction->member->id;
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

    public function setLimit(Interaction           $interaction,
                             bool                  $cost,
                             int|float|string|null $limit, string $timePeriod,
                             bool                  $perUser, ?bool $timeOut,
                             ?string               $message,
                             ?Role                 $role, ?Channel $channel,
                             bool                  $set = true): ?string
    {
        $table = $cost ? BotDatabaseTable::BOT_AI_COST_LIMITS : BotDatabaseTable::BOT_AI_MESSAGE_LIMITS;
        $objectChannel = $this->plan->channels->getIfHasAccess($channel ?? $interaction->channel, $interaction->member);
        $timePeriod = trim($timePeriod);

        if ($objectChannel === null || $objectChannel->ai_model_id === null) {
            return "Could not find AI model related to channel.";
        } else if ($set) {
            $query = get_sql_query(
                $table,
                array("id"),
                array(
                    array("deletion_date", null),
                    array("server_id", $interaction->guild_id),
                    array("channel_id", $channel?->id),
                    array("thread_id", null),
                    array("role_id", $role?->id),
                    array("past_lookup", $timePeriod),
                    array("user", $perUser),
                    array("ai_model_id", $objectChannel->ai_model_id),
                    null,
                    array("expiration_date", "IS", null, 0),
                    array("expiration_date", ">", get_current_date()),
                    null
                )
            );

            if (empty($query)) {
                if (sql_insert(
                    $table,
                    array(
                        "ai_model_id" => $objectChannel->ai_model_id,
                        "server_id" => $interaction->guild_id,
                        "channel_id" => $channel?->id,
                        "role_id" => $role?->id,
                        "past_lookup" => $timePeriod,
                        "user" => $perUser,
                        "limit" => $limit,
                        "timeout" => $timeOut,
                        "message" => $message,
                        "creation_date" => get_current_date(),
                        "created_by" => $interaction->member->id
                    )
                )) {
                    return null;
                } else {
                    return "Failed to set limit associated in the database.";
                }
            } else {
                foreach ($query as $row) {
                    if (!set_sql_query(
                        $table,
                        array(
                            "limit" => $limit,
                            "timeout" => $timeOut,
                            "message" => $message
                        ),
                        array(
                            array("id", $row->id)
                        ),
                        null,
                        1
                    )) {
                        return "Failed to update limit associated in the database.";
                    }
                }
                return null;
            }
        } else if (set_sql_query(
            $table,
            array(
                "deletion_date" => get_current_date(),
                "deleted_by" => $interaction->member->id
            ),
            array(
                array("deletion_date", null),
                array("server_id", $interaction->guild_id),
                array("channel_id", $channel?->id),
                array("thread_id", null),
                array("role_id", $role?->id),
                array("past_lookup", $timePeriod),
                array("user", $perUser),
                array("ai_model_id", $objectChannel->ai_model_id),
                null,
                array("expiration_date", "IS", null, 0),
                array("expiration_date", ">", get_current_date()),
                null
            )
        )) {
            return null;
        } else {
            return "Failed to deleted any limit associated from the database.";
        }
    }
}