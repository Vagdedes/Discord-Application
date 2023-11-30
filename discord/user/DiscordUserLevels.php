<?php

class DiscordUserLevels
{
    private DiscordPlan $plan;
    private array $configurations;

    public const
        CHAT_CHARACTER_POINTS = "chat_character_points",
        VOICE_SECOND_POINTS = "voice_second_points",
        ATTACHMENT_POINTS = "attachment_points",
        REACTION_POINTS = "reaction_points",
        INVITE_USE_POINTS = "invite_use_points";

    public function __construct(DiscordPlan $plan)
    {
        $this->plan = $plan;
        $this->configurations = get_sql_query(
            BotDatabaseTable::BOT_LEVELS,
            null,
            array(
                array("deletion_date", null),
                null,
                array("plan_id", "IS", null, 0),
                array("plan_id", $this->plan->planID),
                null,
                null,
                array("expiration_date", "IS", null, 0),
                array("expiration_date", ">", get_current_date()),
                null
            )
        );

        if (!empty($this->configurations)) {
            global $logger;

            foreach ($this->configurations as $arrayKey => $row) {
                $channelsQuery = get_sql_query(
                    BotDatabaseTable::BOT_LEVEL_CHANNELS,
                    null,
                    array(
                        array("deletion_date", null),
                        array("level_id", $row->id),
                        null,
                        array("expiration_date", "IS", null, 0),
                        array("expiration_date", ">", get_current_date()),
                        null
                    )
                );
                $tiersQuery = get_sql_query(
                    BotDatabaseTable::BOT_LEVEL_TIERS,
                    null,
                    array(
                        array("deletion_date", null),
                        array("level_id", $row->id),
                        null,
                        array("expiration_date", "IS", null, 0),
                        array("expiration_date", ">", get_current_date()),
                        null
                    )
                );
                unset($this->configurations[$arrayKey]);

                if (!empty($channelsQuery) && !empty($tiersQuery)) {
                    foreach ($tiersQuery as $tierKey => $tierValue) {
                        unset($tiersQuery[$tierKey]);
                        $tiersQuery[$tierValue->tier_points] = $tierValue;
                    }
                    krsort($tiersQuery);
                    $row->tiers = $channelsQuery;
                    $row->channels = $channelsQuery;

                    foreach ($channelsQuery as $channel) {
                        $this->configurations[$this->hash($channel->server_id, $channel->channel_id)] = $row;
                    }
                } else {
                    $logger->logError(
                        $this->plan->planID,
                        "No channels found for level with ID: " . $row->id
                    );
                }
            }
        }
    }

    public function getTier(int|string $serverID, int|string $channelID,
                            int|string $userID): ?object
    {
        $level = $this->getLevel($serverID, $channelID, $userID);

        if ($level !== null) {
            $configuration = $this->configurations[$this->hash($serverID, $channelID)] ?? null;

            if ($configuration !== null) {
                foreach ($configuration->tiers as $tier) {
                    if ($level >= $tier->tier_points) {
                        return $tier;
                    }
                }
            }
        }
        return null;
    }

    public function runLevel(int|string $serverID, int|string $channelID,
                             int|string $userID,
                             string     $type, object $reference): void
    {
        if (!empty($this->configurations)) {
            if (!$this->hasCooldown($serverID, $channelID, $userID)) {
                switch ($type) {
                    case self::CHAT_CHARACTER_POINTS:
                        break;
                    case self::VOICE_SECOND_POINTS:
                        break;
                    case self::ATTACHMENT_POINTS:
                        break;
                    case self::REACTION_POINTS:
                        break;
                    case self::INVITE_USE_POINTS:
                        break;
                    default:
                        break;
                }
            }
        }
    }

    public function increaseLevel(int|string $serverID, int|string $channelID,
                                  int|string $userID,
                                  int|string $amount): ?string
    {
        $level = $this->getLevel($serverID, $userID);
        return $level === null
            ? "Could not retrieve level of user."
            : $this->setLevel($serverID, $channelID, $userID, $level + $amount);
    }

    public function decreaseLevel(int|string $serverID, int|string $channelID,
                                  int|string $userID,
                                  int|string $amount): ?string
    {
        $level = $this->getLevel($serverID, $userID);
        return $level === null
            ? "Could not retrieve level of user."
            : $this->setLevel($serverID, $channelID, $userID, max($level - $amount, 0));
    }

    public function setLevel(int|string $serverID, int|string $channelID,
                             int|string $userID,
                             int|string $amount): ?string
    {
        return null;
    }

    public function resetLevel(int|string $serverID, int|string $channelID,
                               int|string $userID): ?string
    {
        return $this->setLevel($serverID, $channelID, $userID, 0);
    }

    private function getLevel(int|string $serverID, int|string $channelID,
                              int|string $userID): ?int
    {
        //todo
        return 0;
    }

    private function hasCooldown(int|string $serverID, int|string $channelID,
                                 int|string $userID): bool
    {
        $configuration = $this->configurations[$serverID] ?? null;

        if ($configuration === null) {
            return true;
        } else {
            if ($configuration->point_cooldown !== null) {
                $cacheKey = array(__METHOD__, $configuration->id, $serverID, $channelID, $userID);
                return !has_memory_cooldown($cacheKey, $configuration->point_cooldown);
            }
            return false;
        }
    }

    private function hash(int|string|null $serverID, int|string|null $channelID): int
    {
        return string_to_integer(
            (empty($serverID) ? "" : $serverID)
            . (empty($channelID) ? "" : $channelID)
        );
    }
}