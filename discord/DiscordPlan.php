<?php

use Discord\Parts\Channel\Message;

class DiscordPlan
{
    public int $planID;
    public string $creationDate;
    public ?string $expirationDate, $creationReason, $expirationReason,
        $messageRetention, $messageCooldown,
        $promptMessage, $cooldownMessage, $failureMessage;
    public array $channels, $whitelistContents;
    public DiscordKnowledge $knowledge;
    public DiscordInstructions $instructions;
    public DiscordConversation $conversation;
    public DiscordModeration $moderation;
    public DiscordLimits $limits;

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
        $this->creationDate = $query->creation_date;
        $this->creationReason = $query->creation_reason;
        $this->expirationDate = $query->expiration_date;
        $this->expirationReason = $query->expiration_reason;
        $this->promptMessage = $query->prompt_message;
        $this->cooldownMessage = $query->cooldown_message;
        $this->failureMessage = $query->failure_message;

        $this->knowledge = new DiscordKnowledge($this);
        $this->instructions = new DiscordInstructions($this);
        $this->conversation = new DiscordConversation($this);
        $this->moderation = new DiscordModeration($this);
        $this->limits = new DiscordLimits($this);

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

    public function canAssist($serverID, $channelID, $userID): bool
    {
        if ($this->moderation->hasPunishment(DiscordPunishment::CUSTOM_BLACKLIST, $userID) !== null) {
            return false;
        }
        $cacheKey = array(__METHOD__, $this->planID, $serverID, $channelID, $userID);
        $cache = get_key_value_pair($cacheKey);

        if ($cache !== null) {
            return $cache;
        }
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
        set_key_value_pair($cacheKey, $result);
        return $result;
    }

    public function assist(ChatAI $chatAI, Message $message,
                                  $serverID, $channelID, $threadID, $userID,
                                  $messageID, $messageContent, $botID): ?string
    {
        $assistance = null;
        $cooldownKey = array(__METHOD__, $this->planID, $userID);

        if (get_key_value_pair($cooldownKey) === null) {
            set_key_value_pair($cooldownKey, true);

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
                $reply = $chatAI->getResult(
                    overflow_long(overflow_long($this->planID * 31) + $userID),
                    array(
                        "messages" => array(
                            array(
                                "role" => "system",
                                "content" => $this->instructions->build($object)
                            ),
                            array(
                                "role" => "user",
                                "content" => $messageContent
                            )
                        )
                    )
                );

                if ($reply[1] !== null) {
                    $assistance = $chatAI->getText($reply[0], $reply[1]);

                    if ($assistance !== null) {
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
                        );
                        set_key_value_pair($cacheKey, $assistance, $this->messageRetention);
                    }
                }

                if ($assistance === null && $this->failureMessage !== null) {
                    $assistance = $this->instructions->replace(array($this->failureMessage), $object)[0];
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
        return $assistance;
    }
}