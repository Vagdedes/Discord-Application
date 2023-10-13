<?php

class DiscordPlan
{
    public int $planID;
    public string $creationDate;
    public ?string $expirationDate, $creationReason, $expirationReason, $messageRetention, $messageCooldown;
    public array $channels, $whitelistContents, $punishmentTypes, $punishments;
    public DiscordKnowledge $knowledge;
    public DiscordInstructions $instructions;

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
        $this->knowledge = new DiscordKnowledge($this);
        $this->instructions = new DiscordInstructions($this);

        // Separator

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

        // Separator

        $this->refreshWhitelist();

        // Separator

        $this->refreshPunishments();
    }

    public function refreshWhitelist(): void
    {
        $this->whitelistContents = get_sql_query(
            BotDatabaseTable::BOT_WHITELIST,
            null,
            array(
                array("plan_id", $this->planID),
                array("deletion_date", null),
                null,
                array("expiration_date", "IS", null, 0),
                array("expiration_date", ">", get_current_date()),
                null
            )
        );
        clear_memory(array(self::class . "::canAssist"), true);
    }

    public function refreshPunishments(): void
    {
        $this->punishmentTypes = get_sql_query(
            BotDatabaseTable::BOT_PUNISHMENT_TYPES,
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

        if (!empty($this->punishmentTypes)) {
            $query = get_sql_query(
                BotDatabaseTable::BOT_PUNISHMENTS,
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

            if (!empty($query)) {
                $punishments = array();

                foreach ($query as $punishment) {
                    foreach ($this->punishmentTypes as $punishmentType) {
                        if ($punishment->type == $punishmentType->id) {
                            $punishment->type = $punishmentType;
                            $punishments[] = $punishment;
                            break;
                        }
                    }
                }
                $this->punishments = $punishments;
            } else {
                $this->punishments = array();
            }
        } else {
            $this->punishments = array();
        }
        clear_memory(array(
            self::class . "::getPunishments",
            self::class . "::hasPunishment"
        ), true);
    }

    // Separator

    public function hasPunishment(?int $type, $userID): ?object
    {
        $cacheKey = array(__METHOD__, $this->planID, $type, $userID);
        $cache = get_key_value_pair($cacheKey);

        if ($cache !== null) {
            return $cache === false ? null : $cache;
        }
        $object = null;

        if (!empty($this->punishments)) {
            foreach ($this->punishments as $punishment) {
                if ($punishment->user_id == $userID
                    && ($type === null || $punishment->type == $type)) {
                    $object = $punishment;
                }
            }
        }
        set_key_value_pair($cacheKey, $object);
        return $object;
    }

    // Separator

    public function getPunishments($userID): array
    {
        $cacheKey = array(__METHOD__, $userID);
        $cache = get_key_value_pair($cacheKey);

        if ($cache !== null) {
            return $cache;
        }
        $array = array();

        if (!empty($this->punishments)) {
            foreach ($this->punishments as $punishment) {
                if ($punishment->user_id == $userID) {
                    $array[] = $punishment;
                }
            }
        }
        set_key_value_pair($cacheKey, $array);
        return $array;
    }

    public function getMessages($userID, ?int $limit = 0): array
    {
        set_sql_cache("1 second");
        return get_sql_query(
            BotDatabaseTable::BOT_MESSAGES,
            null,
            array(
                array("plan_id", $this->planID),
                array("user_id", $userID),
                array("deletion_date", null),
            ),
            array(
                "DESC",
                "id"
            ),
            $limit
        );
    }

    public function getReplies($userID, ?int $limit = 0): array
    {
        set_sql_cache("1 second");
        return get_sql_query(
            BotDatabaseTable::BOT_REPLIES,
            null,
            array(
                array("plan_id", $this->planID),
                array("user_id", $userID),
                array("deletion_date", null),
            ),
            array(
                "DESC",
                "id"
            ),
            $limit
        );
    }

    public function getConversation($userID, ?int $limit = 0): array
    {
        $final = array();
        $messages = $this->getMessages($userID, $limit);
        $replies = $this->getReplies($userID, $limit);

        if (!empty($messages)) {
            foreach ($messages as $row) {
                $final[strtotime($row->creation_date)] = $row;
            }
        }
        if (!empty($replies)) {
            foreach ($replies as $row) {
                $final[strtotime($row->creation_date)] = $row;
            }
        }
        krsort($final);
        return $final;
    }

    // Separator

    public function addReply($botID, $serverID, $channelID, $userID, $messageID, $message): void
    {
        global $scheduler;
        $scheduler->addTask(
            null,
            "sql_insert",
            array(
                BotDatabaseTable::BOT_REPLIES,
                array(
                    "plan_id" => $this->planID,
                    "bot_id" => $botID,
                    "server_id" => $serverID,
                    "channel_id" => $channelID,
                    "user_id" => $userID,
                    "message_id" => $messageID,
                    "message_content" => $message,
                    "creation_date" => get_current_date(),
                )
            )
        );
    }

    public function addMessage($botID, $serverID, $channelID, $userID, $messageID, $message): void
    {
        global $scheduler;
        $scheduler->addTask(
            null,
            "sql_insert",
            array(
                BotDatabaseTable::BOT_MESSAGES,
                array(
                    "plan_id" => $this->planID,
                    "bot_id" => $botID,
                    "server_id" => $serverID,
                    "channel_id" => $channelID,
                    "user_id" => $userID,
                    "message_id" => $messageID,
                    "message_content" => $message,
                    "creation_date" => get_current_date(),
                )
            )
        );
    }

    public function addPunishment(?int $type, $botID, $executorID, $userID, $reason, $duration = null): bool
    {
        if (empty($this->punishmentTypes)) {
            return false;
        }
        $found = false;

        foreach ($this->punishmentTypes as $punishmentType) {
            if ($punishmentType->id == $type) {
                $found = true;
                break;
            }
        }

        if ($found) {
            global $scheduler;
            $scheduler->addTask(
                null,
                "sql_insert",
                array(
                    BotDatabaseTable::BOT_PUNISHMENTS,
                    array(
                        "type" => $type,
                        "bot_id" => $botID,
                        "executor_id" => $executorID,
                        "user_id" => $userID,
                        "creation_date" => get_current_date(),
                        "creation_reason" => $reason,
                        "expiration_date" => $duration === null ? null : get_future_date($duration)
                    )
                )
            );
            return true;
        } else {
            return false;
        }
    }

    // Separator

    public function canAssist($serverID, $channelID, $userID): bool
    {
        if ($this->hasPunishment(DiscordPunishment::CUSTOM_BLACKLIST, $userID) !== null) {
            return false;
        }
        $cacheKey = array(__METHOD__, $serverID, $channelID, $userID);
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
        set_key_value_pair($cacheKey, $result, 60);
        return $result;
    }

    public function assist(ChatAI $chatAI, $serverID, $channelID, $userID,
                                  $messageID, $message, $botID): ?string
    {
        $assistance = null;
        $cooldownKey = array(__METHOD__, $this->planID, $userID);

        if (get_key_value_pair($cooldownKey) === null) {
            set_key_value_pair($cooldownKey, true);
            $cacheKey = array(__METHOD__, $this->planID, $userID, $message);
            $cache = get_key_value_pair($cacheKey);

            if ($cache !== null) {
                $assistance = $cache;
            } else {
                $reply = $chatAI->getResult(
                    overflow_long(overflow_long($this->planID * 31) + $userID),
                    array(
                        "messages" => array(
                            array(
                                "role" => "system",
                                "content" => $this->instructions->build($serverID, $channelID, $userID, $message, $botID)
                            ),
                            array(
                                "role" => "user",
                                "content" => $message
                            )
                        )
                    )
                );

                if ($reply[1] !== null) {
                    $assistance = $chatAI->getText($reply[0], $reply[1]);

                    if ($assistance !== null) {
                        $this->addMessage(
                            $botID,
                            $serverID,
                            $channelID,
                            $userID,
                            $messageID,
                            $message,
                        );
                        $this->addReply(
                            $botID,
                            $serverID,
                            $channelID,
                            $userID,
                            $messageID,
                            $assistance,
                        );
                        set_key_value_pair($cacheKey, $assistance, $this->messageRetention);
                    }
                }
            }
            set_key_value_pair($cooldownKey, true, $this->messageCooldown);
        }
        return $assistance;
    }
}