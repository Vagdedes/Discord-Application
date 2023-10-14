<?php

use Discord\Parts\Channel\Message;

class DiscordPlan
{
    public int $planID;
    public bool $strictReply, $requireMention;
    private bool $debug;
    public string $name, $description, $creationDate;
    public ?string $expirationDate, $creationReason, $expirationReason;
    private ?string $messageRetention, $messageCooldown,
        $promptMessage, $cooldownMessage, $failureMessage,
        $requireStartingText, $requireContainedText, $requireEndingText;
    private array $channels, $whitelistContents;
    public DiscordKnowledge $knowledge;
    public DiscordInstructions $instructions;
    public DiscordConversation $conversation;
    public DiscordModeration $moderation;
    public DiscordLimits $limits;
    public DiscordCommands $commands;

    public function __construct($planID)
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

        $this->planID = (int)$query->id;
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
        $this->debug = $query->debug !== null;

        $this->knowledge = new DiscordKnowledge($this);
        $this->instructions = new DiscordInstructions($this);
        $this->conversation = new DiscordConversation($this);
        $this->moderation = new DiscordModeration($this);
        $this->limits = new DiscordLimits($this);
        $this->commands = new DiscordCommands($this);

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
        clear_memory(array(self::class), true);
    }

    // Separator

    public function canAssist($mentions, $serverID, $channelID, $userID, $messageContent, $botID): bool
    {
        if (!$this->requireMention) {
            $result = true;
        } else if (!empty($mentions)) {
            $result = false;

            foreach ($mentions as $user) {
                if ($user->id == $botID) {
                    $result = true;
                    break;
                }
            }

            if ($result) {
                $messageContent = str_replace("<@" . $botID . ">", "", $messageContent);
            }
        } else {
            $result = false;
        }

        if ($result) {
            $cacheKey = array(__METHOD__, $this->planID, $serverID, $channelID, $userID);
            $cache = get_key_value_pair($cacheKey);

            if ($cache !== null) {
                $result = $cache;
            } else {
                if ($this->requireStartingText !== null) {
                    $result &= starts_with($messageContent, $this->requireStartingText);
                }
                if ($result && $this->requireContainedText !== null) {
                    $result &= str_contains($messageContent, $this->requireContainedText);
                }
                if ($result && $this->requireEndingText !== null) {
                    $result &= ends_with($messageContent, $this->requireEndingText);
                }

                if ($result) {
                    $result = false;

                    if (!empty($this->channels)) {
                        foreach ($this->channels as $channel) {
                            if ($channel->server_id == $serverID
                                && $channel->channel_id == $channelID) {
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
        }
        return $result;
    }

    public function assist(ChatAI $chatAI, Message $message,
                                  $serverID, $channelID, $threadID, $userID,
                                  $messageID, $messageContent, $botID): ?string
    {
        $assistance = null;
        $punishment = $this->moderation->hasPunishment(DiscordPunishment::CUSTOM_BLACKLIST, $userID);

        if ($punishment !== null) {
            if ($punishment->notify !== null) {
                $object = $this->instructions->getObject(
                    $serverID,
                    $channelID,
                    $threadID,
                    $userID,
                    $messageContent,
                    $messageID,
                    $botID
                );
                $assistance = $this->instructions->replace(array($punishment->creation_reason), $object)[0];
            }
        } else {
            $limits = $this->limits->isLimited($serverID, $channelID, $userID, $botID);

            if (!empty($limits)) {
                foreach ($limits as $limit) {
                    if ($limit->limit_type->message !== null) {
                        $object = $this->instructions->getObject(
                            $serverID,
                            $channelID,
                            $threadID,
                            $userID,
                            $messageContent,
                            $messageID,
                            $botID
                        );
                        $assistance = $this->instructions->replace(array($limit->limit_type->message), $object)[0];
                        break;
                    }
                }
            } else {
                $cooldownKey = array(__METHOD__, $this->planID, $userID);

                if (get_key_value_pair($cooldownKey) === null) {
                    set_key_value_pair($cooldownKey, true);
                    $assistance = $this->commands->process($serverID, $channelID, $userID, $messageContent);

                    if ($assistance !== null) {
                        $object = $this->instructions->getObject(
                            $serverID,
                            $channelID,
                            $threadID,
                            $userID,
                            $messageContent,
                            $messageID,
                            $botID
                        );
                        $assistance = $this->instructions->replace(array($assistance), $object)[0];
                    } else {
                        if ($this->promptMessage !== null) {
                            $object = $this->instructions->getObject(
                                $serverID,
                                $channelID,
                                $threadID,
                                $userID,
                                $messageContent,
                                $messageID,
                                $botID
                            );
                            $message->reply($this->instructions->replace(array($this->promptMessage), $object)[0]);
                        }
                        $cacheKey = array(__METHOD__, $this->planID, $userID, $messageContent);
                        $cache = get_key_value_pair($cacheKey);

                        if ($cache !== null) {
                            $assistance = $cache;
                        } else {
                            if (!isset($object)) {
                                $object = $this->instructions->getObject(
                                    $serverID,
                                    $channelID,
                                    $threadID,
                                    $userID,
                                    $messageContent,
                                    $messageID,
                                    $botID
                                );
                            }
                            $instructions = $this->instructions->build($object);
                            $reply = $chatAI->getResult(
                                overflow_long(overflow_long($this->planID * 31) + $userID),
                                array(
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
                                )
                            );

                            if ($reply[1] !== null) {
                                $model = $reply[0];
                                $modelReply = $reply[1];
                                $assistance = $chatAI->getText($model, $modelReply);

                                if ($assistance !== null) {
                                    if ($this->debug && $assistance !== DiscordProperties::NO_REPLY) {
                                        $assistance = substr(
                                            DiscordSyntax::HEAVY_CODE_BLOCK . $instructions . DiscordSyntax::HEAVY_CODE_BLOCK . $assistance,
                                            0,
                                            DiscordProperties::MESSAGE_MAX_LENGTH
                                        );
                                    }
                                    $this->conversation->addMessage(
                                        $botID,
                                        $serverID,
                                        $channelID,
                                        $threadID,
                                        $userID,
                                        $messageID,
                                        $messageContent,
                                    );
                                    $this->conversation->addReply(
                                        $botID,
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
                                }
                            }

                            if ($assistance === null && $this->failureMessage !== null) {
                                $assistance = $this->instructions->replace(array($this->failureMessage), $object)[0];
                            }
                        }
                    }
                    set_key_value_pair($cooldownKey, true, $this->messageCooldown);
                } else if ($this->cooldownMessage !== null) {
                    $object = $this->instructions->getObject(
                        $serverID,
                        $channelID,
                        $threadID,
                        $userID,
                        $messageContent,
                        $messageID,
                        $botID
                    );
                    $assistance = $this->instructions->replace(array($this->cooldownMessage), $object)[0];
                }
            }
        }
        return $assistance;
    }

}