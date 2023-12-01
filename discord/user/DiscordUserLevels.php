<?php

use Discord\Parts\Channel\Message;

class DiscordUserLevels
{
    private DiscordPlan $plan;
    private array $configurations;

    private const
        REFRESH_TIME = "15 seconds",
        NOT_FOUND = "Could not find a levelling system related to this server and channel.";

    public const
        CHAT_CHARACTER_POINTS = "chat_character_points",
        VOICE_SECOND_POINTS = "voice_second_points",
        ATTACHMENT_POINTS = "attachment_points",
        REACTION_POINTS = "reaction_points",
        INVITE_USE_POINTS = "invite_use_points";

    //todo commands
    //todo apply runLevel

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
                            int|string $userID): object|string
    {
        $configuration = $this->configurations[$this->hash($serverID, $channelID)];

        if ($configuration !== null) {
            $level = $this->getLevel($serverID, $channelID, $userID);

            foreach ($this->configurations[$this->hash($serverID, $channelID)]->tiers as $tier) {
                if ($level[1] >= $tier->tier_points) {
                    return $tier;
                }
            }
            return "Could not find the user's tier.";
        } else {
            return self::NOT_FOUND;
        }
    }

    public function runLevel(int|string $serverID, int|string $channelID,
                             int|string $userID,
                             string     $type, object $reference): void
    {
        if (!$this->hasCooldown($serverID, $channelID, $userID)) {
            $configuration = $this->configurations[$this->hash($serverID, $channelID)];

            switch ($type) {
                case self::CHAT_CHARACTER_POINTS:
                    if ($reference instanceof Message) {
                        $this->increaseLevel(
                            $serverID,
                            $channelID,
                            $userID,
                            strlen($reference->content) * $configuration->{$type}
                        );
                    }
                    break;
                case self::ATTACHMENT_POINTS:
                    if ($reference instanceof Message) {
                        $this->increaseLevel(
                            $serverID,
                            $channelID,
                            $userID,
                            sizeof($reference->attachments->toArray()) * $configuration->{$type}
                        );
                    }
                    break;
                case self::REACTION_POINTS:
                    $this->increaseLevel(
                        $serverID,
                        $channelID,
                        $userID,
                        $configuration->{$type}
                    );
                    break;
                case self::INVITE_USE_POINTS:
                case self::VOICE_SECOND_POINTS:
                    $this->increaseLevel(
                        $serverID,
                        $channelID,
                        $userID,
                        $reference
                    );
                    break;
                default:
                    break;
            }
        }
    }

    public function increaseLevel(int|string $serverID, int|string $channelID,
                                  int|string $userID,
                                  int|string $amount): ?string
    {
        return $this->setLevel($serverID, $channelID, $userID,
            $this->getLevel($serverID, $channelID, $userID)[1] + $amount);
    }

    public function decreaseLevel(int|string $serverID, int|string $channelID,
                                  int|string $userID,
                                  int|string $amount): ?string
    {
        return $this->setLevel($serverID, $channelID, $userID, max(
            $this->getLevel($serverID, $channelID, $userID)[1] - $amount, 0));
    }

    public function setLevel(int|string $serverID, int|string $channelID,
                             int|string $userID,
                             int|string $amount): ?string
    {
        $configuration = $this->configurations[$this->hash($serverID, $channelID)];

        if ($configuration !== null) {
            $level = $this->getLevel($serverID, $channelID, $userID);

            if ($level[0]) {
                if (set_sql_query(
                    BotDatabaseTable::BOT_LEVEL_TRACKING,
                    array(
                        "level_points" => $amount,
                        "expiration_date" => $configuration->points_duration !== null
                            ? get_future_date($configuration->points_duration) : null
                    ),
                    array(
                        array("level_id", $configuration->id),
                        array("user_id", $userID),
                        array("deletion_date", null),
                    ),
                    null,
                    1
                )) {
                    return null;
                } else {
                    return "Could not update the user's level to the database.";
                }
            } else if (sql_insert(
                BotDatabaseTable::BOT_LEVEL_TRACKING,
                array(
                    "level_id" => $configuration->id,
                    "user_id" => $userID,
                    "level_points" => $amount,
                    "creation_date" => get_current_date(),
                    "expiration_date" => $configuration->points_duration !== null
                        ? get_future_date($configuration->points_duration) : null
                )
            )) {
                return null;
            } else {
                return "Could not the user's insert level to the database.";
            }
        } else {
            return self::NOT_FOUND;
        }
    }

    public function resetLevel(int|string $serverID, int|string $channelID,
                               int|string $userID): ?string
    {
        return $this->setLevel($serverID, $channelID, $userID, 0);
    }

    private function getLevel(int|string $serverID, int|string $channelID,
                              int|string $userID, bool $cache = false): array
    {
        $configuration = $this->configurations[$this->hash($serverID, $channelID)];

        if ($configuration !== null) {
            if ($cache) {
                set_sql_cache(self::REFRESH_TIME);
            }
            $query = get_sql_query(
                BotDatabaseTable::BOT_LEVEL_TRACKING,
                array("level_points", "expiration_date"),
                array(
                    array("deletion_date", null),
                    array("user_id", $userID),
                    array("level_id", $configuration->id)
                ),
                null,
                1
            );

            if (empty($query)) {
                return array(false, 0);
            } else {
                $query = $query[0];
                return array(
                    true,
                    $query->expiration_date !== null && $query->expiration_date > get_current_date()
                        ? 0
                        : $query->level_points
                );
            }
        } else {
            return array(false, 0);
        }
    }

    private function getLevels(int|string $serverID, int|string $channelID): array
    {
        $configuration = $this->configurations[$this->hash($serverID, $channelID)] ?? null;

        if ($configuration !== null) {
            $array = array();
            set_sql_cache(self::REFRESH_TIME);
            $query = get_sql_query(
                BotDatabaseTable::BOT_LEVEL_TRACKING,
                null,
                array(
                    array("deletion_date", null),
                    array("level_id", $configuration->id),
                    null,
                    array("expiration_date", "IS", null, 0),
                    array("expiration_date", ">", get_current_date()),
                    null
                ),
                null,
                1
            );

            if (!empty($query)) {
                //todo
            }
            return $array;
        } else {
            return array();
        }
    }

    private function hasCooldown(int|string $serverID, int|string $channelID,
                                 int|string $userID): bool
    {
        $configuration = $this->configurations[$this->hash($serverID, $channelID)] ?? null;

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
        $cacheKey = array(__METHOD__, $serverID, $channelID);
        $cache = get_key_value_pair($cacheKey);

        if ($cache !== null) {
            return $cache;
        } else {
            $hash = string_to_integer(
                (empty($serverID) ? "" : $serverID)
                . (empty($channelID) ? "" : $channelID)
            );
            set_key_value_pair($cacheKey, $hash);
            return $hash;
        }
    }
}