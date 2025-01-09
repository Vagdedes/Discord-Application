<?php

use Discord\Builders\MessageBuilder;
use Discord\Parts\Channel\Invite;
use Discord\Parts\Guild\Guild;

class DiscordInviteTracker
{
    private DiscordBot $bot;
    private array $goals;

    private static array $cached_invites = array();
    private static int $invite_links = 0, $invited_users = 0;

    public function __construct(DiscordBot $bot)
    {
        $this->bot = $bot;
        $this->goals = get_sql_query(
            BotDatabaseTable::BOT_INVITE_TRACKER_GOALS,
            null,
            array(
                array("deletion_date", null),
                null,
                array("expiration_date", "IS", null, 0),
                array("expiration_date", ">", get_current_date()),
                null
            )
        );
        self::$cached_invites = array();
        self::$invite_links = 0;
        self::$invited_users = 0;

        foreach ($this->bot->discord->guilds as $guild) {
            self::track($this->bot, $guild, $this);
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

    public function getUserStats(Guild $guild, int|string $userID): object
    {
        $object = new stdClass();
        $object->total_invite_links = 0;
        $object->active_invite_links = 0;
        $object->users_invited = 0;
        $query = self::getInvites($guild);

        if (!empty($query)) {
            $date = get_current_date();

            foreach ($query as $invite) {
                if ($userID !== null
                    && $invite->inviter !== null
                    && $invite->inviter->id == $userID) {
                    $object->total_invite_links++;
                    $object->users_invited += $invite->uses;

                    if ($invite->expires_at === null || $invite->expires_at > $date) {
                        $object->active_invite_links++;
                    }
                }
            }
        }
        return $object;
    }

    public function getServerStats(Guild $guild): array
    {
        $query = self::getInvites($guild);

        if (!empty($query)) {
            $array = array();
            $finalArray = array();
            $date = get_current_date();

            foreach ($query as $invite) {
                if (!array_key_exists($invite->inviter?->id, $array)) {
                    $object = new stdClass();
                    $object->user_id = $invite->inviter?->id;
                    $object->total_invite_links = 1;
                    $object->active_invite_links = $invite->expires_at === null || $invite->expires_at > $date ? 1 : 0;
                    $object->users_invited = $invite->uses;
                    $array[$invite->inviter?->id] = $object;
                } else {
                    $object = $array[$invite->inviter?->id];
                    $object->total_invite_links++;
                    $object->users_invited += $invite->uses;

                    if ($invite->expires_at === null || $invite->expires_at > $date) {
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

    public static function getInvite(Guild $guild): ?Invite
    {
        $invites = self::getInvites($guild);
        return array_shift($invites);
    }

    public static function getInvites(Guild $guild): array
    {
        return self::$cached_invites[$guild->id] ?? array();
    }

    public static function track(DiscordBot $bot, ?Guild $guild, ?DiscordInviteTracker $inviteTracker = null, ?callable $callable = null): void
    {
        if ($guild === null) {
            return;
        }
        $guild->getInvites()->done($bot->utilities->oneArgumentFunction(
            function (mixed $invites) use ($guild, $callable, $inviteTracker) {
                $cached = array();

                foreach ($invites as $invite) {
                    $code = $invite->code;
                    $cached[$code] = $invite;
                    $totalUses = $invite->uses ?? 0;
                    $serverID = $guild->id;
                    $userID = $invite->inviter?->id ?? null;
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
                        if ($userID !== null) {
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

                            if (!empty($inviteTracker?->goals)) {
                                for ($target = 1; $target <= $totalUses; $target++) {
                                    self::triggerGoal($invite, $inviteTracker, $serverID, $userID, $target);
                                }
                            }
                        }
                    } else {
                        $query = $query[0];
                        $difference = $totalUses - $query->uses;

                        if ($difference > 0) {
                            $channelID = $invite?->channel_id;

                            if ($channelID !== null) {
                                if ($inviteTracker !== null) {
                                    $inviteTracker->bot->userLevels->runLevel(
                                        $serverID,
                                        $invite->channel,
                                        $invite->inviter,
                                        DiscordUserLevels::INVITE_USE_POINTS,
                                        $difference
                                    );
                                }
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
                                && !empty($inviteTracker?->goals)
                                && $userID !== null) {
                                for ($target = $query->uses + 1; $target <= $totalUses; $target++) {
                                    self::triggerGoal($invite, $inviteTracker, $serverID, $userID, $target);
                                }
                            }
                        }
                    }
                }
                self::$cached_invites[$guild->id] = $cached;

                if ($callable !== null) {
                    $callable();
                }
            }
        ));
    }

    private static function triggerGoal(Invite               $invite,
                                        DiscordInviteTracker $inviteTracker,
                                        int|string           $serverID, int|string $userID,
                                        int                  $target): void
    {
        foreach ($inviteTracker->goals as $goal) {
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
                    $object = $inviteTracker->bot->instructions->getObject(
                        $invite->guild,
                        $invite->channel,
                        $invite->inviter
                    );
                    $messageBuilder = $goal->message_name !== null
                        ? $inviteTracker->bot->persistentMessages->get($object, $goal->message_name)
                        : MessageBuilder::new()->setContent(
                            $inviteTracker->bot->instructions->replace(array($goal->message_content), $object)[0]
                        );

                    if ($messageBuilder !== null) {
                        $channel = $inviteTracker->bot->discord->getChannel($goal->message_channel_id);

                        if ($channel !== null
                            && $channel->guild_id === $goal->message_server_id) {
                            $channel->sendMessage($messageBuilder);
                        }
                    } else {
                        $inviteTracker->bot->listener->callInviteTrackerImplementation(
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