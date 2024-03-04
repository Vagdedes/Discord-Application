<?php

use Discord\Builders\MessageBuilder;
use Discord\Parts\Channel\Invite;
use Discord\Parts\Guild\Guild;

class DiscordInviteTracker
{
    private DiscordPlan $plan;
    private array $goals;

    private static array $cached_invites  =array();
    private static int $invite_links = 0, $invited_users = 0;
    private static bool $isInitialized = false;

    private const REFRESH_TIME = "15 seconds";

    public function __construct(DiscordPlan $plan)
    {
        $this->plan = $plan;
        $this->goals = get_sql_query(
            BotDatabaseTable::BOT_INVITE_TRACKER_GOALS,
            null,
            array(
                array("deletion_date", null),
                array("plan_id", $plan->planID),
                null,
                array("expiration_date", "IS", null, 0),
                array("expiration_date", ">", get_current_date()),
                null
            )
        );

        if (!self::$isInitialized) {
            self::$isInitialized = true;

            foreach ($this->plan->bot->discord->guilds as $guild) {
                $this->track($guild);
            }
        }
    }

    public static function getInviteLinks(): int
    {
        return self::$invite_links;
    }

    public static function getInvitedUsers(): int
    {
        return self::$invited_users;
    }

    public function getUserStats(int|string $serverID, int|string $userID): object
    {
        $object = new stdClass();
        $object->total_invite_links = 0;
        $object->active_invite_links = 0;
        $object->users_invited = 0;

        set_sql_cache(self::REFRESH_TIME);
        $query = get_sql_query(
            BotDatabaseTable::BOT_INVITE_TRACKER,
            null,
            array(
                array("server_id", $serverID),
                array("user_id", $userID),
                array("deletion_date", null)
            )
        );

        if (!empty($query)) {
            $date = get_current_date();

            foreach ($query as $row) {
                $object->total_invite_links++;
                $object->users_invited += $row->uses;

                if ($row->expiration_date === null || $row->expiration_date > $date) {
                    $object->active_invite_links++;
                }
            }
        }
        return $object;
    }

    public function getServerStats(int|string $serverID): array
    {
        set_sql_cache(self::REFRESH_TIME);
        $query = get_sql_query(
            BotDatabaseTable::BOT_INVITE_TRACKER,
            null,
            array(
                array("server_id", $serverID),
                array("deletion_date", null),
                array("uses", ">", 0)
            ),
            array(
                "DESC",
                "uses"
            )
        );

        if (!empty($query)) {
            $array = array();
            $finalArray = array();
            $date = get_current_date();

            foreach ($query as $row) {
                if (!array_key_exists($row->user_id, $array)) {
                    $object = new stdClass();
                    $object->user_id = $row->user_id;
                    $object->total_invite_links = 1;
                    $object->active_invite_links = $row->expiration_date === null || $row->expiration_date > $date ? 1 : 0;
                    $object->users_invited = $row->uses;
                    $array[$row->user_id] = $object;
                } else {
                    $object = $array[$row->user_id];
                    $object->total_invite_links++;
                    $object->users_invited += $row->uses;

                    if ($row->expiration_date === null || $row->expiration_date > $date) {
                        $object->active_invite_links++;
                    }
                }
            }

            foreach ($array as $object) {
                $position = $object->users_invited;

                while (true) {
                    if (!array_key_exists($position, $finalArray)) {
                        $finalArray[$position] = $object;
                        break;
                    } else {
                        $position--;
                    }
                }
            }
            krsort($finalArray);
            return $finalArray;
        } else {
            return array();
        }
    }

    public static function getInvite(Guild $guild): ?string
    {
        return self::$cached_invites[$guild->id][0] ?? null;
    }

    public function track(Guild $guild): void
    {
        $guild->getInvites()->done(function (mixed $invites) use ($guild) {
            $cached = array();

            foreach ($invites as $invite) {
                $cached[] = $invite->invite_url;
                $totalUses = $invite->uses ?? null;
                $code = $invite->code ?? null;
                $serverID = $invite->guild_id ?? null;
                $userID = $invite->inviter?->id ?? null;

                if ($totalUses !== null && $code !== null
                    && $serverID !== null && $userID !== null) {
                    self::$invite_links++;
                    self::$invited_users += $totalUses;
                    $query = get_sql_query(
                        BotDatabaseTable::BOT_INVITE_TRACKER,
                        array("id", "uses"),
                        array(
                            array("server_id", $serverID),
                            array("invite_code", $code),
                            array("deletion_date", null)
                        ),
                        array(
                            "DESC",
                            "id"
                        ),
                        1
                    );

                    if (empty($query)) {
                        sql_insert(
                            BotDatabaseTable::BOT_INVITE_TRACKER,
                            array(
                                "server_id" => $serverID,
                                "user_id" => $userID,
                                "invite_code" => $code,
                                "uses" => $totalUses,
                                "creation_date" => $invite->created_at,
                                "expiration_date" => $invite->expires_at
                            )
                        );

                        if (!empty($this->goals)) {
                            for ($target = 1; $target <= $totalUses; $target++) {
                                $this->triggerGoal($invite, $serverID, $userID, $target);
                            }
                        }
                    } else {
                        $query = $query[0];
                        $difference = $totalUses - $query->uses;

                        if ($difference > 0) {
                            $channelID = $invite?->channel_id;

                            if ($channelID !== null) {
                                $this->plan->userLevels->runLevel(
                                    $serverID,
                                    $invite->channel,
                                    $invite->inviter,
                                    DiscordUserLevels::INVITE_USE_POINTS,
                                    $difference
                                );
                            }
                            if (set_sql_query(
                                    BotDatabaseTable::BOT_INVITE_TRACKER,
                                    array(
                                        "uses" => $totalUses,
                                    ),
                                    array(
                                        array("id", $query->id)
                                    ),
                                    null,
                                    1
                                )
                                && !empty($this->goals)) {
                                for ($target = $query->uses + 1; $target <= $totalUses; $target++) {
                                    $this->triggerGoal($invite, $serverID, $userID, $target);
                                }
                            }
                        }
                    }
                }
            }
            self::$cached_invites[$guild->id] = $cached;
        });
    }

    private function triggerGoal(Invite     $invite,
                                 int|string $serverID, int|string $userID,
                                 int        $target): void
    {
        foreach ($this->goals as $goal) {
            if ($goal->target_invited_users == $target) {
                if ($goal->max_goals !== null) {
                    $query = get_sql_query(
                        BotDatabaseTable::BOT_INVITE_TRACKER_GOAL_STORAGE,
                        array("id"),
                        array(
                            array("goal_id", $goal->id),
                            array("server_id", $serverID),
                            array("user_id", $userID),
                            array("deletion_date", null)
                        ),
                        null,
                        $goal->max_goals
                    );

                    if (sizeof($query) == $goal->max_goals) {
                        break;
                    }
                }
                if (sql_insert(
                    BotDatabaseTable::BOT_INVITE_TRACKER_GOAL_STORAGE,
                    array(
                        "goal_id" => $goal->id,
                        "server_id" => $serverID,
                        "user_id" => $userID,
                        "creation_date" => get_current_date()
                    )
                )) {
                    $object = $this->plan->instructions->getObject(
                        $invite->guild,
                        $invite->channel,
                        $invite->inviter
                    );
                    $messageBuilder = $goal->message_name !== null
                        ? $this->plan->persistentMessages->get($object, $goal->message_name)
                        : MessageBuilder::new()->setContent(
                            $this->plan->instructions->replace(array($goal->message_content), $object)[0]
                        );

                    if ($messageBuilder !== null) {
                        $channel = $this->plan->bot->discord->getChannel($goal->message_channel_id);

                        if ($channel !== null
                            && $channel->guild_id === $goal->message_server_id) {
                            $channel->sendMessage($messageBuilder);
                        }
                    } else {
                        $this->plan->listener->callInviteTrackerImplementation(
                            $goal->listener_class,
                            $goal->listener_method,
                            $invite
                        );
                    }
                }
                break;
            }
        }
    }

    public function getStoredGoals(int|string $serverID, int|string $userID, int $limit = 0): array
    {
        if (!empty($this->goals)) {
            $array = array();
            $hasLimit = $limit > 0;

            foreach ($this->goals as $goal) {
                set_sql_cache(self::REFRESH_TIME);
                $storage = get_sql_query(
                    BotDatabaseTable::BOT_INVITE_TRACKER_GOAL_STORAGE,
                    null,
                    array(
                        array("goal_id", $goal->id),
                        array("server_id", $serverID),
                        array("user_id", $userID),
                        array("deletion_date", null),
                    ),
                    array(
                        "DESC",
                        "id"
                    ),
                    $goal->max_goals ?? 0
                );

                if (!empty($storage)) {
                    $array[] = $goal;

                    if ($hasLimit && sizeof($array) == $limit) {
                        return $array;
                    }
                }
            }
            return $array;
        }
        return array();
    }

}