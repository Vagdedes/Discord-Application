<?php

use Discord\Discord;
use Discord\Parts\Channel\Message;

class DiscordPlan
{
    public int $planID, $botID;
    public ?int $applicationID, $family, $minMessageLength, $maxMessageLength;
    public bool $strictReply, $requireMention;
    private bool $debug;
    public string $name, $creationDate;
    public ?string $description, $expirationDate, $creationReason, $expirationReason;
    private ?string $messageRetention, $messageCooldown,
        $promptMessage, $cooldownMessage, $failureMessage,
        $requireStartingText, $requireContainedText, $requireEndingText;
    private array $channels, $whitelistContents, $keywords, $mentions;
    private ?ChatAI $chatAI;
    public Discord $discord;
    public DiscordInstructions $instructions;
    public DiscordConversation $conversation;
    public DiscordModeration $moderation;
    public DiscordLimits $limits;
    public DiscordCommands $commands;
    public DiscordListener $listener;
    public DiscordComponent $component;
    public DiscordControlledMessages $controlledMessages;

    public function __construct(Discord $discord, int|string $botID, int|string $planID)
    {
        $query = get_sql_query(
            BotDatabaseTable::BOT_PLANS,
            null,
            array(
                array("id", $planID),
            ),
            null,
            1
        );
        $query = $query[0];

        $this->discord = $discord;
        $this->botID = $botID;
        $this->planID = (int)$query->id;
        $this->family = $query->family === null ? null : (int)$query->family;
        $this->applicationID = $query->application_id === null ? null : (int)$query->application_id;
        $this->messageRetention = $query->message_retention;
        $this->messageCooldown = $query->message_cooldown;
        $this->name = $query->name;
        $this->description = $query->description;
        $this->creationDate = $query->creation_date;
        $this->creationReason = $query->creation_reason;
        $this->expirationDate = $query->expiration_date;
        $this->expirationReason = $query->expiration_reason;
        $this->promptMessage = $query->prompt_message;
        $this->cooldownMessage = $query->cooldown_message;
        $this->failureMessage = $query->failure_message;
        $this->requireStartingText = $query->require_starting_text;
        $this->requireContainedText = $query->require_contained_text;
        $this->requireEndingText = $query->require_ending_text;
        $this->requireMention = $query->require_mention !== null;
        $this->strictReply = $query->strict_reply !== null;
        $this->debug = false;
        $this->minMessageLength = $query->min_message_length;
        $this->maxMessageLength = $query->max_message_length;

        $this->instructions = new DiscordInstructions($this);
        $this->conversation = new DiscordConversation($this);
        $this->moderation = new DiscordModeration($this);
        $this->limits = new DiscordLimits($this);
        $this->commands = new DiscordCommands($this);
        $this->listener = new DiscordListener($this);
        $this->component = new DiscordComponent($this);
        $this->controlledMessages = new DiscordControlledMessages($this);

        $this->keywords = get_sql_query(
            BotDatabaseTable::BOT_KEYWORDS,
            null,
            array(
                array("deletion_date", null),
                null,
                array("plan_id", "IS", null, 0),
                array("plan_id", $this->planID),
                null,
                null,
                array("expiration_date", "IS", null, 0),
                array("expiration_date", ">", get_current_date()),
                null
            )
        );
        if ($this->requireMention) {
            $this->mentions = get_sql_query(
                BotDatabaseTable::BOT_MENTIONS,
                null,
                array(
                    array("deletion_date", null),
                    null,
                    array("plan_id", "IS", null, 0),
                    array("plan_id", $this->planID),
                    null,
                    null,
                    array("expiration_date", "IS", null, 0),
                    array("expiration_date", ">", get_current_date()),
                    null
                )
            );
        } else {
            $this->mentions = array();
        }
        $this->channels = get_sql_query(
            BotDatabaseTable::BOT_CHANNELS,
            null,
            array(
                array("deletion_date", null),
                null,
                array("plan_id", "IS", null, 0),
                array("plan_id", $this->planID),
                null,
                null,
                array("expiration_date", "IS", null, 0),
                array("expiration_date", ">", get_current_date()),
                null
            )
        );
        $this->whitelistContents = get_sql_query(
            BotDatabaseTable::BOT_WHITELIST,
            null,
            array(
                array("deletion_date", null),
                null,
                array("plan_id", "IS", null, 0),
                array("plan_id", $this->planID),
                null,
                null,
                array("expiration_date", "IS", null, 0),
                array("expiration_date", ">", get_current_date()),
                null
            )
        );
        $query = get_sql_query(
            BotDatabaseTable::BOT_CHAT_MODEL,
            null,
            array(
                array("plan_id", $this->planID),
                array("deletion_date", null)
            ),
            null,
            1
        );

        if (!empty($query)) {
            global $AI_key;
            $query = $query[0];
            $this->chatAI = new ChatAI(
                $query->model_family,
                $AI_key[0],
                DiscordProperties::MESSAGE_MAX_LENGTH,
                $query->temperature,
                $query->frequency_penalty,
                $query->presence_penalty,
                $query->completions,
                $query->top_p,
            );
        } else {
            $this->chatAI = null;
        }
    }

    // Separator

    public function canAssist(int|string $serverID, int|string $channelID, int|string $userID,
                              string     $messageContent): bool
    {
        $cacheKey = array(__METHOD__, $this->planID, $serverID, $channelID, $userID);
        $cache = get_key_value_pair($cacheKey);

        if ($cache !== null) {
            $result = $cache;
        } else {
            $result = true;

            if ($this->requireStartingText !== null) {
                $result = starts_with($messageContent, $this->requireStartingText);
            }
            if ($result && $this->requireContainedText !== null) {
                $result = str_contains($messageContent, $this->requireContainedText);
            }
            if ($result && $this->requireEndingText !== null) {
                $result = ends_with($messageContent, $this->requireEndingText);
            }
            if ($result && $this->minMessageLength !== null) {
                $result = strlen($messageContent) >= $this->minMessageLength;
            }
            if ($result && $this->maxMessageLength !== null) {
                $result = strlen($messageContent) <= $this->maxMessageLength;
            }
            if ($result && !empty($this->keywords)) {
                foreach ($this->keywords as $keyword) {
                    if ($keyword->keyword !== null) {
                        if (str_contains($messageContent, $keyword->keyword)) {
                            $result = true;
                            break;
                        }
                    }
                }
            }
            if ($result) {
                $result = false;

                if (!empty($this->channels)) {
                    foreach ($this->channels as $channel) {
                        if ($channel->server_id == $serverID
                            && $channel->channel_id == $channelID) {
                            $this->debug = $channel->debug !== null;

                            if ($channel->whitelist === null) {
                                $result = true;
                                break;
                            } else if (!empty($this->whitelistContents)) {
                                foreach ($this->whitelistContents as $whitelist) {
                                    if ($whitelist->user_id == $userID
                                        && ($whitelist->server_id === null
                                            || $whitelist->server_id === $serverID
                                            && ($whitelist->channel_id === null
                                                || $whitelist->channel_id === $channelID))) {
                                        $result = true;
                                        break 2;
                                    }
                                }
                            } else {
                                break;
                            }
                        }
                    }
                }
            }
            set_key_value_pair($cacheKey, $result);
        }
        return $result;
    }

    public function assist(Message $message,
                                           $mentions,
                           int|string      $serverID, string $serverName,
                           int|string      $channelID, string $channelName,
                           int|string|null $threadID, string|null $threadName,
                           int|string      $userID, string $userName, ?string $displayname,
                           int|string      $messageID, string $messageContent,
                           string          $botName): ?string
    {
        $assistance = null;
        $punishment = $this->moderation->hasPunishment(DiscordPunishment::CUSTOM_BLACKLIST, $userID);
        $object = $this->instructions->getObject(
            $serverID,
            $serverName,
            $channelID,
            $channelName,
            $threadID,
            $threadName,
            $userID,
            $userName,
            $displayname,
            $messageContent,
            $messageID,
            $botName
        );

        if ($punishment !== null) {
            if ($punishment->notify !== null) {
                $assistance = $this->instructions->replace(array($punishment->creation_reason), $object)[0];
            }
        } else {
            $cooldownKey = array(__METHOD__, $this->planID, $userID);

            if (get_key_value_pair($cooldownKey) === null) {
                global $logger;
                set_key_value_pair($cooldownKey, true);

                if ($this->chatAI !== null && $this->chatAI->exists) {
                    $assistance = $this->commands->process(
                        $serverID,
                        $channelID,
                        $userID,
                        $messageID,
                        $messageContent
                    );

                    if ($assistance !== null) {
                        $assistance = $this->instructions->replace(array($assistance), $object)[0];
                    } else {
                        if ($userID != $this->botID) {
                            if ($this->requireMention) {
                                $mention = false;

                                if (!empty($mentions)) {
                                    foreach ($mentions as $user) {
                                        if ($user->id == $this->botID) {
                                            $mention = true;
                                            break;
                                        }
                                    }

                                    if ($mention) {
                                        $messageContent = str_replace("<@" . $this->botID . ">", "", $messageContent);
                                    } else if (!empty($this->mentions)) {
                                        foreach ($this->mentions as $alternativeMention) {
                                            foreach ($mentions as $user) {
                                                if ($user->id == $alternativeMention->user_id) {
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
                            $limits = $this->limits->isLimited($serverID, $channelID, $userID);

                            if (!empty($limits)) {
                                foreach ($limits as $limit) {
                                    if ($limit->message !== null) {
                                        $assistance = $this->instructions->replace(array($limit->message), $object)[0];
                                        break;
                                    }
                                }
                            } else {
                                if ($this->promptMessage !== null) {
                                    $message->reply($this->instructions->replace(array($this->promptMessage), $object)[0]);
                                }
                                $cacheKey = array(__METHOD__, $this->planID, $userID, $messageContent);
                                $cache = get_key_value_pair($cacheKey);

                                if ($cache !== null) {
                                    $assistance = $cache;
                                } else {
                                    $instructions = $this->instructions->build($object);
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
                                        overflow_long(overflow_long($this->planID * 31) + $userID),
                                        $parameters
                                    );
                                    $modelReply = $reply[2];

                                    if ($this->debug) {
                                        foreach (array($parameters, $modelReply) as $debug) {
                                            foreach (str_split(json_encode($debug), DiscordProperties::MESSAGE_MAX_LENGTH) as $split) {
                                                $message->reply(str_replace("\\n", DiscordProperties::NEW_LINE, $split));
                                            }
                                        }
                                    }
                                    if ($reply[0]) {
                                        $model = $reply[1];
                                        $assistance = $this->chatAI->getText($model, $modelReply);

                                        if ($assistance !== null) {
                                            $this->conversation->addMessage(
                                                $serverID,
                                                $channelID,
                                                $threadID,
                                                $userID,
                                                $messageID,
                                                $messageContent,
                                            );
                                            $this->conversation->addReply(
                                                $serverID,
                                                $channelID,
                                                $threadID,
                                                $userID,
                                                $messageID,
                                                $assistance,
                                                ($modelReply->usage->prompt_tokens * $model->sent_token_cost) + ($modelReply->usage->completion_tokens * $model->received_token_cost),
                                                $model->currency->code
                                            );
                                            set_key_value_pair($cacheKey, $assistance, $this->messageRetention);
                                        } else {
                                            $logger->logError($this->planID, "Failed to get text from chat-model for plan: " . $this->planID);
                                        }
                                    } else {
                                        $logger->logError($this->planID, $modelReply);
                                    }

                                    if ($assistance === null && $this->failureMessage !== null) {
                                        $assistance = $this->instructions->replace(array($this->failureMessage), $object)[0];
                                    }
                                }
                            }
                        }
                    }
                } else if ($this->failureMessage !== null) {
                    $logger->logError($this->planID, "Failed to find chat-model for plan: " . $this->planID);
                    $assistance = $this->instructions->replace(array($this->failureMessage), $object)[0];
                }
                set_key_value_pair($cooldownKey, true, $this->messageCooldown);
            } else if ($this->cooldownMessage !== null) {
                $assistance = $this->instructions->replace(array($this->cooldownMessage), $object)[0];
            }
        }
        return $assistance;
    }

    public function welcome(int|string $serverID, int|string $userID): void
    {
        if (!has_memory_limit(
                array(__METHOD__, $this->planID, $serverID, $userID),
                1
            )
            && !empty($this->channels)) {
            foreach ($this->channels as $channel) {
                if ($channel->server_id == $serverID
                    && $channel->welcome_message !== null) {
                    if ($channel->whitelist === null) {
                        $channelFound = $this->discord->getChannel($channel->channel_id);

                        if ($channelFound !== null
                            && $channelFound->allowText()) {
                            $channelFound->sendMessage("<@$userID> " . $channel->welcome_message);
                        }
                    } else if (!empty($this->whitelistContents)) {
                        foreach ($this->whitelistContents as $whitelist) {
                            if ($whitelist->user_id == $userID
                                && ($whitelist->server_id === null
                                    || $whitelist->server_id === $serverID
                                    && ($whitelist->channel_id === null
                                        || $whitelist->channel_id === $channel->channel_id))) {
                                $channelFound = $this->discord->getChannel($channel->channel_id);

                                if ($channelFound !== null
                                    && $channelFound->allowText()) {
                                    $channelFound->sendMessage("<@$userID> " . $channel->welcome_message);
                                }
                                break;
                            }
                        }
                    }
                }
            }
        }
    }
}