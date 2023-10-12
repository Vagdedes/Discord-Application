<?php

class DiscordPlan
{
    public int $planID;
    public string $creationDate;
    public ?string $creationReason;
    public ?string $expirationDate;
    public ?string $expirationReason;
    public ?string $deletionDate;
    public ?string $deletionReason;
    public array $channels;
    public array $whitelistContents;
    public array $punishmentTypes;
    public array $punishments;
    public DiscordKnowledge $knowledge;
    public DiscordInstructions $instructions;

    public function __construct($planID)
    {
        $query = get_sql_query(
            DatabaseVariables::BOT_PLANS_TABLE,
            null,
            array(
                array("id", $planID),
            ),
            null,
            1
        );
        $query = $query[0];
        $this->planID = (int)$query->id;
        $this->creationDate = $query->creation_date;
        $this->creationReason = $query->creation_reason;
        $this->expirationDate = $query->expiration_date;
        $this->expirationReason = $query->expiration_reason;
        $this->deletionDate = $query->deletion_date;
        $this->deletionReason = $query->deletion_reason;
        $this->knowledge = new DiscordKnowledge($this);
        $this->instructions = new DiscordInstructions($this);

        // Separator

        $this->channels = get_sql_query(
            DatabaseVariables::BOT_CHANNELS_TABLE,
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

        // Separator

        $this->refreshWhitelist();

        // Separator

        $this->punishmentTypes = get_sql_query(
            DatabaseVariables::BOT_PUNISHMENT_TYPES_TABLE,
            null,
            array(
                null,
                array("plan_id", "IS", null, 0),
                array("plan_id", $this->planID),
                null,
                array("deletion_date", null),
                null,
                array("expiration_date", "IS", null, 0),
                array("expiration_date", ">", get_current_date()),
                null
            )
        );
        $this->refreshPunishments();
    }

    public function refreshWhitelist(): void
    {
        $this->whitelistContents = get_sql_query(
            DatabaseVariables::BOT_WHITELIST_TABLE,
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
        if (!empty($this->punishmentTypes)) {
            $query = get_sql_query(
                DatabaseVariables::BOT_PUNISHMENTS_TABLE,
                null,
                array(
                    array("bot_id", $this->botID),
                    array("deletion_date", null),
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
    }

    public function getMessages($userID, ?int $limit = 0): array
    {
        set_sql_cache("1 second");
        return get_sql_query(
            DatabaseVariables::BOT_MESSAGES_TABLE,
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
            DatabaseVariables::BOT_REPLIES_TABLE,
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
        rsort($final);
        return $final;
    }

    public function canAssist($serverID, $channelID, $userID): bool
    {
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
}