<?php

use Discord\Parts\Channel\Channel;
use Discord\Parts\Channel\Message;
use Discord\Parts\Guild\Guild;
use Discord\Parts\User\Member;
use Discord\Parts\WebSockets\MessageReaction;

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
                            int|string $userID, int|string|null $level = null,
                            bool       $cache = false): array|string
    {
        $configuration = $this->configurations[$this->hash($serverID, $channelID)];

        if ($configuration !== null) {
            if ($level === null) {
                $level = $this->getLevel($serverID, $channelID, $userID, $cache)[1];
            }
            foreach ($this->configurations[$this->hash($serverID, $channelID)]->tiers as $tier) {
                if ($level >= $tier->tier_points) {
                    return array($tier, $level);
                }
            }
            return "Could not find the user's tier.";
        } else {
            return self::NOT_FOUND;
        }
    }

    public function runLevel(int|string $serverID, Channel $channel,
                             Member     $user,
                             string     $type, mixed $reference): void
    {
        if (!$this->hasCooldown($serverID, $channel->id, $user->id)) {
            $configuration = $this->configurations[$this->hash($serverID, $channel->id)];

            switch ($type) {
                case self::CHAT_CHARACTER_POINTS:
                    if ($reference instanceof Message) {
                        $outcome = $this->increaseLevel(
                            $serverID,
                            $channel->id,
                            $user->id,
                            strlen($reference->content) * $configuration->{$type}
                        );
                    } else {
                        $outcome = false;
                    }
                    break;
                case self::ATTACHMENT_POINTS:
                    if ($reference instanceof Message) {
                        $outcome = $this->increaseLevel(
                            $serverID,
                            $channel->id,
                            $user->id,
                            sizeof($reference->attachments->toArray()) * $configuration->{$type}
                        );
                    } else {
                        $outcome = false;
                    }
                    break;
                case self::REACTION_POINTS:
                    if ($reference instanceof MessageReaction) {
                        $outcome = $this->increaseLevel(
                            $serverID,
                            $channel->id,
                            $user->id,
                            $configuration->{$type}
                        );
                    } else {
                        $outcome = false;
                    }
                    break;
                case self::VOICE_SECOND_POINTS:
                    $outcome = $this->increaseLevel(
                        $serverID,
                        $channel->id,
                        $user->id,
                        $configuration->{$type}
                    );
                    break;
                case self::INVITE_USE_POINTS:
                    $outcome = $this->increaseLevel(
                        $serverID,
                        $channel->id,
                        $user->id,
                        $reference * $configuration->{$type}
                    );
                    break;
                default:
                    $outcome = false;
                    break;
            }

            if (is_array($outcome)) {
                if ($configuration->notification_channel_id !== null) {
                    $channel = $this->plan->bot->discord->getChannel($configuration->notification_channel_id);
                    $proceed = $channel !== null
                        && $channel->allowText()
                        && $channel->guild_id == $serverID;
                } else {
                    $proceed = true;
                }

                if ($proceed) {
                    $messageBuilder = $this->plan->utilities->buildMessageFromObject(
                        $configuration,
                        $this->plan->instructions->getObject(
                            $channel->guild,
                            $channel,
                            $reference instanceof Message
                                ? $reference->thread
                                : ($reference instanceof MessageReaction ? $reference->message->thread : null),
                            $user,
                            $reference instanceof Message
                                ? $reference
                                : ($reference instanceof MessageReaction ? $reference->message : null),
                        )
                    );

                    if ($messageBuilder !== null) {
                        $channel->sendMessage($messageBuilder);
                    } else {
                        global $logger;
                        $logger->logError(
                            $this->plan->planID,
                            "Incorrect notification message with configuration ID: " . $configuration->id
                        );
                    }
                }

                $newTier = $outcome[1];
                $this->plan->listener->callUserLevelsImplementation(
                    $newTier->listener_class,
                    $newTier->listener_method,
                    $channel,
                    $configuration,
                    $outcome[0],
                    $newTier
                );
            }
        }
    }

    public function trackVoiceChannels(Guild $guild): void
    {
        if (!empty($guild->channels->first())) {
            foreach ($guild->channels as $channel) {
                if ($channel->allowVoice() && !empty($channel->members->first())) {
                    foreach ($channel->members as $member) {
                        if ($guild?->id !== null
                            && $channel?->id !== null
                            && $member?->id !== null) {
                            $this->runLevel(
                                $guild->id,
                                $channel,
                                $member,
                                self::VOICE_SECOND_POINTS,
                                $channel
                            );
                        }
                    }
                }
            }
        }
    }

    public function increaseLevel(int|string $serverID, int|string $channelID,
                                  int|string $userID,
                                  int|string $amount): string|array|null
    {
        return $this->setLevel($serverID, $channelID, $userID,
            $this->getLevel($serverID, $channelID, $userID)[1] + $amount);
    }

    public function decreaseLevel(int|string $serverID, int|string $channelID,
                                  int|string $userID,
                                  int|string $amount): string|array|null
    {
        return $this->setLevel($serverID, $channelID, $userID, max(
            $this->getLevel($serverID, $channelID, $userID)[1] - $amount, 0));
    }

    public function setLevel(int|string $serverID, int|string $channelID,
                             int|string $userID,
                             int|string $amount): string|array|null
    {
        $configuration = $this->configurations[$this->hash($serverID, $channelID)];

        if ($configuration !== null) {
            $level = $this->getLevel($serverID, $channelID, $userID);

            if ($level[0]) {
                if (!set_sql_query(
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
                    return "Could not update the user's level to the database.";
                }
            } else if (!sql_insert(
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
                return "Could not the user's insert level to the database.";
            }
            $currentTier = $this->getTier($serverID, $channelID, $userID, $level[0]);

            if (is_array($currentTier)) {
                $newTier = $this->getTier($serverID, $channelID, $userID, $amount);

                if (is_array($newTier)) {
                    if ($currentTier[0]->id !== $newTier[0]->id) {
                        return array($currentTier[0], $newTier[0]);
                    } else {
                        return null;
                    }
                } else {
                    return "Could not calculate the user's new tier: " . $newTier;
                }
            } else {
                return "Could not find the user's current tier: " . $currentTier;
            }
        } else {
            return self::NOT_FOUND;
        }
    }

    public function resetLevel(int|string $serverID, int|string $channelID,
                               int|string $userID): string|array|null
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

    public function getLevels(int|string $serverID, int|string $channelID): array|string
    {
        $configuration = $this->configurations[$this->hash($serverID, $channelID)] ?? null;

        if ($configuration !== null) {
            set_sql_cache(self::REFRESH_TIME);
            $query = get_sql_query(
                BotDatabaseTable::BOT_LEVEL_TRACKING,
                null,
                array(
                    array("deletion_date", null),
                    array("level_id", $configuration->id),
                )
            );

            if (!empty($query)) {
                $array = array();
                $date = get_current_date();

                foreach ($query as $row) {
                    $position = $row->expiration_date === null || $row->expiration_date > $date
                        ? $row->level_points
                        : 0;
                    $row->level_points = $position;
                    $row->tier = $this->getTier($serverID, $channelID, $row->user_id, $position, true)[0];

                    while (true) {
                        if (!array_key_exists($position, $array)) {
                            $array[$position] = $row;
                            break;
                        } else {
                            $position--;
                        }
                    }
                }
                krsort($array);
                return $array;
            } else {
                return "Could not find any levels for this server and channel.";
            }
        } else {
            return self::NOT_FOUND;
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

    private function hash(int|string|null $id1, int|string|null $id2): int
    {
        $cacheKey = array(__METHOD__, $id1, $id2);
        $cache = get_key_value_pair($cacheKey);

        if ($cache !== null) {
            return $cache;
        } else {
            $hash = string_to_integer(
                (empty($id1) ? "" : $id1)
                . (empty($id2) ? "" : $id2)
            );
            set_key_value_pair($cacheKey, $hash);
            return $hash;
        }
    }
}