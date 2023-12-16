<?php

use Discord\Parts\Channel\Channel;
use Discord\Parts\Permissions\Permission;
use Discord\Parts\User\Member;
use Discord\Parts\User\User;
use Discord\Parts\WebSockets\VoiceStateUpdate;

class DiscordTemporaryChannels
{
    private DiscordPlan $plan;
    private array $channels;
    public int $ignoreDeletion;

    private const
        NOT_IN_CHANNEL = "You are not in a voice channel.",
        NOT_IN_TEMPORARY_CHANNEL = "You are not in a temporary voice channel.",
        OWNER_PERMISSIONS = array(
        Permission::ALL_PERMISSIONS["manage_channels"]
    ),
        BANNED_MEMBER_PERMISSIONS = array(
        Permission::VOICE_PERMISSIONS["connect"],
        Permission::VOICE_PERMISSIONS["speak"]
    );

    // todo commands

    public function __construct(DiscordPlan $plan)
    {
        $this->ignoreDeletion = 0;
        $this->plan = $plan;
        $this->channels = get_sql_query(
            BotDatabaseTable::BOT_TEMPORARY_CHANNELS,
            null,
            array(
                array("plan_id", $plan->planID),
                array("deletion_date", null),
                null,
                array("expiration_date", "IS", null, 0),
                array("expiration_date", ">", get_current_date()),
                null
            )
        );

        if (!empty($this->channels)) {
            foreach ($this->channels as $arrayKey => $row) {
                unset($this->channels[$arrayKey]);
                $this->channels[$row->id] = $row;
            }
        }
        $this->checkExpired();
    }

    public function trackJoin(VoiceStateUpdate $update): bool
    {
        $state = $this->getChannelState($update->channel);

        if (!empty($state)) {
            if ($state[0]) {
                $query = $state[1]->inception_channel;

                if ($query->max_open === null
                    || !$this->hasMaxOpen($query->id, $query->max_open)
                    || $query->close_oldest_if_max_open !== null && $this->closeOldest($query)) {
                    $date = get_current_date();

                    while (true) {
                        $temporaryID = random_number(19);

                        if (empty(get_sql_query(
                            BotDatabaseTable::BOT_TEMPORARY_CHANNEL_TRACKING,
                            array("temporary_channel_creation_id"),
                            array(
                                array("temporary_channel_creation_id", $temporaryID)
                            ),
                            null,
                            1
                        ))) {
                            $rolePermissions = get_sql_query(
                                BotDatabaseTable::BOT_TEMPORARY_CHANNEL_ROLES,
                                array("allow", "deny", "role_id"),
                                array(
                                    array("deletion_date", null),
                                    array("temporary_channel_id", $state[1]->id),
                                )
                            );

                            if (!empty($rolePermissions)) {
                                foreach ($rolePermissions as $arrayKey => $role) {
                                    $rolePermissions[$arrayKey] = array(
                                        "id" => $role->role_id,
                                        "type" => "role",
                                        "allow" => empty($role->allow) ? $query->allow_permission : $role->allow,
                                        "deny" => empty($role->deny) ? $query->deny_permission : $role->deny
                                    );
                                }
                            }
                            $this->plan->utilities->createChannel(
                                $update->guild,
                                Channel::TYPE_TEXT,
                                $query->inception_channel_category_id,
                                $update->member->username . "'s Channel",
                                $query->inception_channel_topic,
                                $rolePermissions,
                            )->done(function (Channel $channel) use ($update, $query, $temporaryID, $date) {
                                if (sql_insert(
                                    BotDatabaseTable::BOT_TEMPORARY_CHANNEL_TRACKING,
                                    array(
                                        "temporary_channel_id" => $query->id,
                                        "temporary_channel_creation_id" => $temporaryID,
                                        "server_id" => $channel->guild,
                                        "channel_id" => $channel->id,
                                        "creation_date" => $date,
                                        "expiration_date" => $query->duration !== null ? get_future_date($query->duration) : null,
                                    )
                                )) {
                                    $outcome = $this->setOwner($update->member, $update->user, true, null, $channel, true);

                                    if ($outcome !== null) {
                                        global $logger;
                                        $logger->logError($this->plan->planID, $outcome);
                                        $this->closeByChannel($channel);
                                    }
                                } else {
                                    global $logger;
                                    $logger->logError(
                                        $this->plan->planID,
                                        "Failed to insert temporary channel into the database with ID: " . $query->id
                                    );
                                    $this->closeByChannel($channel);
                                }
                            });
                        }
                    }
                }
            } else if ($this->isBanned($update->user, $state[1]->temporary_channel_creation_id)) {
                $this->kick($update->user, $update->channel, $state);
            }
            $this->checkExpired();
            return true;
        }
        return false;
    }

    public function trackLeave(VoiceStateUpdate $update): bool
    {
        $state = $this->getChannelState($update->channel);

        if (!empty($state)) {
            if (!$state[0]) {
                $channel = $update->channel;
                $members = $channel->members->toArray();
                unset($members[$update->user->id]);

                if (empty($members)) {
                    set_sql_query(
                        BotDatabaseTable::BOT_TEMPORARY_CHANNEL_TRACKING,
                        array(
                            "deletion_date" => get_current_date(),
                        ),
                        array(
                            array("deletion_date", null),
                            array("temporary_channel_creation_id", $state[1]->temporary_channel_creation_id),
                            null,
                            array("expiration_date", "IS", null, 0),
                            array("expiration_date", ">", get_current_date()),
                            null
                        ),
                        null,
                        1
                    );
                    $this->ignoreDeletion++;
                    $channel->guild->channels->delete($channel);
                } else {
                    $owner = $this->getOwner($update->user, $state[1]->temporary_channel_creation_id);

                    if (!empty($owner) && $owner[0]->created_by === null) {
                        $this->setOwner($update->member, $update->user, false, null, $channel, true);

                        if ($this->getOwners($state[1]->temporary_channel_creation_id) === 0) {
                            $member = array_shift($members);
                            $outcome = $this->setOwner($member, $member->user, true, null, $channel, true);

                            if ($outcome !== null) {
                                global $logger;
                                $logger->logError($this->plan->planID, $outcome);
                                $this->closeByChannel($channel);
                            }
                        }
                    }
                }
            }
            $this->checkExpired();
            return true;
        }
        return false;
    }

    // Separator

    public function setOwner(Member  $member, User $user,
                             bool    $set = true, ?string $reason = null,
                             Channel $channel = null, bool $force = false): ?string
    {
        if ($channel === null) {
            $channel = $member->getVoiceChannel();
        }

        if ($channel !== null) {
            $state = $this->getChannelState($channel);

            if (!empty($state) && !$state[0]) {
                if ($set) {
                    if ($this->isBanned($user, $state[1]->temporary_channel_creation_id)) {
                        return "User is banned from this temporary voice channel.";
                    } else if (!$force && !empty($this->getOwner($user, $state[1]->temporary_channel_creation_id))) {
                        return "User is already an owner of this temporary voice channel.";
                    } else {
                        foreach ($channel->members as $member) {
                            if ($member->id == $user->id) {
                                if (sql_insert(
                                    BotDatabaseTable::BOT_TEMPORARY_CHANNEL_OWNERS,
                                    array(
                                        "user_id" => $user->id,
                                        "temporary_channel_id" => $state[1]->temporary_channel_id,
                                        "temporary_channel_creation_id" => $state[1]->temporary_channel_creation_id,
                                        "server_id" => $channel->guild_id,
                                        "channel_id" => $channel->id,
                                        "creation_date" => get_current_date(),
                                        "creation_reason" => $reason,
                                        "created_by" => $member->id == $user->id ? null : $member->id,
                                        "deletion_date" => get_current_date()
                                    )
                                )) {
                                    $channel->setPermissions(
                                        $member,
                                        self::OWNER_PERMISSIONS,
                                    );
                                    return null;
                                } else {
                                    return "Failed to insert owner into the database.";
                                }
                            }
                        }
                        return "Could not find this user in this temporary voice channel.";
                    }
                } else if ($member->id == $user->id) {
                    return "You cannot remove yourself from an owner of this temporary voice channel.";
                } else {
                    $owner = $force ? true : $this->getOwner($user, $state[1]->temporary_channel_creation_id);

                    if (!empty($owner)) {
                        if (!$force && $owner[0]->created_by === null) {
                            return "You cannot remove the original owner of this temporary voice channel.";
                        } else if (set_sql_query(
                            BotDatabaseTable::BOT_TEMPORARY_CHANNEL_OWNERS,
                            array(
                                "deletion_date" => get_current_date(),
                                "deleted_by" => $member->id,
                            ),
                            array(
                                array("deletion_date", null),
                                array("user_id", $user->id),
                                array("server_id", $channel->guild_id),
                                array("channel_id", $channel->id)
                            ),
                            array(
                                "DESC",
                                "id"
                            ),
                            1
                        )) {
                            $channel->setPermissions(
                                $member,
                                array(),
                                $this->isBanned($user, $state[1]->temporary_channel_creation_id)
                                    ? self::BANNED_MEMBER_PERMISSIONS
                                    : array()
                            );
                            return null;
                        } else {
                            return "Failed to modify owner into the database.";
                        }
                    } else {
                        return "User is not an owner of this temporary voice channel.";
                    }
                }
            } else {
                return self::NOT_IN_TEMPORARY_CHANNEL;
            }
        } else {
            return self::NOT_IN_CHANNEL;
        }
    }

    // Separator

    public function setBan(Member $member, User $user, bool $set = true, ?string $reason = null): ?string
    {
        $channel = $member->getVoiceChannel();

        if ($channel !== null) {
            $state = $this->getChannelState($channel);

            if (!empty($state) && !$state[0]) {
                if ($set) {
                    if ($this->isBanned($user, $state[1]->temporary_channel_creation_id)) {
                        return "User is already banned from this temporary voice channel.";
                    } else {
                        $owner = $this->getOwner($user, $state[1]->temporary_channel_creation_id);

                        if (!empty($owner) && $owner[0]->created_by === null) {
                            return "You cannot ban the original owner of this temporary voice channel.";
                        } else if (sql_insert(
                            BotDatabaseTable::BOT_TEMPORARY_CHANNEL_BANS,
                            array(
                                "user_id" => $user->id,
                                "temporary_channel_id" => $state[1]->temporary_channel_id,
                                "temporary_channel_creation_id" => $state[1]->temporary_channel_creation_id,
                                "server_id" => $channel->guild_id,
                                "channel_id" => $channel->id,
                                "creation_date" => get_current_date(),
                                "creation_reason" => $reason,
                                "created_by" => $member->id,
                                "deletion_date" => get_current_date(),
                            )
                        )) {
                            $string = $this->setOwner($member, $user, false);

                            if ($string === null) {
                                $this->kick($user, $channel, $state);
                            }
                            return $string;
                        } else {
                            return "Failed to insert ban into the database.";
                        }
                    }
                } else {
                    if (!$this->isBanned($user, $state[1]->temporary_channel_creation_id)) {
                        return "User is not banned from this temporary voice channel.";
                    } else if (set_sql_query(
                        BotDatabaseTable::BOT_TEMPORARY_CHANNEL_BANS,
                        array(
                            "deletion_date" => get_current_date(),
                            "deleted_by" => $member->id,
                        ),
                        array(
                            array("deletion_date", null),
                            array("user_id", $user->id),
                            array("server_id", $channel->guild_id),
                            array("channel_id", $channel->id)
                        ),
                        array(
                            "DESC",
                            "id"
                        ),
                        1
                    )) {
                        return null;
                    } else {
                        return "Failed to insert unban into the database.";
                    }
                }
            } else {
                return self::NOT_IN_TEMPORARY_CHANNEL;
            }
        } else {
            return self::NOT_IN_CHANNEL;
        }
    }

    private function isBanned(User $user, int|string $temporaryID): bool
    {
        set_sql_cache("1 second");
        return !empty(get_sql_query(
            BotDatabaseTable::BOT_TEMPORARY_CHANNEL_BANS,
            null,
            array(
                array("deletion_date", null),
                array("user_id", $user->id),
                array("temporary_channel_creation_id", $temporaryID)
            ),
            array(
                "DESC",
                "id"
            ),
            1
        ));
    }

    // Separator

    public function closeByChannel(Channel $channel): void
    {
        $state = $this->getChannelState($channel);

        if (!empty($state) && !$state[0]) {
            try {
                if (set_sql_query(
                    BotDatabaseTable::BOT_TEMPORARY_CHANNEL_TRACKING,
                    array(
                        "deletion_date" => get_current_date()
                    ),
                    array(
                        array("id", $state[1]->id)
                    ),
                    null,
                    1
                )) {
                    $this->ignoreDeletion++;
                    $channel->guild->channels->delete($channel);
                }
            } catch (Throwable $exception) {
                global $logger;
                $logger->logError($this->plan->planID, $exception->getMessage());
            }
        }
    }

    // Separator

    private function getOwner(User $user, int|string $temporaryID): array
    {
        set_sql_cache("1 second");
        return get_sql_query(
            BotDatabaseTable::BOT_TEMPORARY_CHANNEL_OWNERS,
            null,
            array(
                array("deletion_date", null),
                array("user_id", $user->id),
                array("temporary_channel_creation_id", $temporaryID)
            ),
            null,
            1
        );
    }

    private function getOwners(int|string $temporaryID): int
    {
        set_sql_cache("1 second");
        return sizeof(get_sql_query(
            BotDatabaseTable::BOT_TEMPORARY_CHANNEL_OWNERS,
            null,
            array(
                array("deletion_date", null),
                array("temporary_channel_id", $temporaryID)
            ),
            array(
                "DESC",
                "id"
            )
        ));
    }

    private function getChannelState(Channel $channel): ?array
    {
        $channelInitiator = $this->channels[$channel->id] ?? null;

        if ($channelInitiator !== null) {
            return array(true, $channelInitiator);
        } else {
            set_sql_cache("1 second");
            $query = get_sql_query(
                BotDatabaseTable::BOT_TEMPORARY_CHANNEL_TRACKING,
                null,
                array(
                    array("deletion_date", null),
                    array("server_id", $channel->guild_id),
                    array("channel_id", $channel->id),
                    null,
                    array("expiration_date", "IS", null, 0),
                    array("expiration_date", ">", get_current_date()),
                    null
                ),
                array(
                    "DESC",
                    "id"
                ),
                1
            );

            if (!empty($query)) {
                $query = $query[0];
                $query->inception_channel = $this->channels[$query->temporary_channel_id] ?? null;
                return array(false, $query);
            }
        }
        return null;
    }

    private function kick(User|Member $member, Channel $channel, array $state): void
    {
        foreach ($channel->members as $channelMember) {
            if ($channelMember->id == $member->id) {
                $channel->setPermissions(
                    $member,
                    array(),
                    self::BANNED_MEMBER_PERMISSIONS
                );
                $inception = $state[1]->inception_channel;

                if ($inception !== null) {
                    $channel = $this->plan->bot->discord->getChannel($inception->inception_channel_id);

                    if ($channel !== null) {
                        $channel->moveMember($member);
                    } else {
                        global $logger;
                        $logger->logError(
                            $this->plan->planID,
                            "Failed to move user to inception channel with ID: " . $inception->id
                        );
                    }
                }
                break;
            }
        }
    }

    private function checkExpired(): void
    {
        $query = get_sql_query(
            BotDatabaseTable::BOT_TEMPORARY_CHANNEL_TRACKING,
            null,
            array(
                array("deletion_date", null),
                array("expired", null),
                array("expiration_date", "<", get_current_date())
            ),
            array(
                "DESC",
                "id"
            ),
            DiscordPredictedLimits::RAPID_CHANNEL_MODIFICATIONS // Limit so we don't ping Discord too much
        );

        if (!empty($query)) {
            foreach ($query as $row) {
                if (set_sql_query(
                    BotDatabaseTable::BOT_TEMPORARY_CHANNEL_TRACKING,
                    array(
                        "expired" => 1
                    ),
                    array(
                        array("id", $row->id)
                    ),
                    null,
                    1
                )) {
                    $channel = $this->plan->bot->discord->getChannel($row->channel_id);

                    if ($channel !== null
                        && $channel->allowText()
                        && $channel->guild_id == $row->server_id) {
                        $this->ignoreDeletion++;
                        $channel->guild->channels->delete($channel);
                    }
                } else {
                    global $logger;
                    $logger->logError(
                        $this->plan->planID,
                        "Failed to close expired temporary channel with ID: " . $row->id
                    );
                }
            }
        }
    }

    private function hasMaxOpen(int|string $temporaryID, int|string $limit): bool
    {
        set_sql_cache("1 second");
        return sizeof(get_sql_query(
                BotDatabaseTable::BOT_TEMPORARY_CHANNEL_TRACKING,
                array("id"),
                array(
                    array("temporary_channel_id", $temporaryID),
                    array("deletion_date", null),
                    array("expired", null),
                    null,
                    array("expiration_date", "IS", null, 0),
                    array("expiration_date", ">", get_current_date()),
                    null
                ),
                array(
                    "DESC",
                    "id"
                ),
                $limit
            )) == $limit;
    }

    private function closeOldest(object $temporary): bool
    {
        $query = get_sql_query(
            BotDatabaseTable::BOT_TEMPORARY_CHANNEL_TRACKING,
            array("id", "server_id", "channel_id", "created_thread_id"),
            array(
                array("deletion_date", null),
                array("expired", null),
                array("temporary_channel_id", $temporary->id),
            ),
            array(
                "ASC",
                "creation_date"
            ),
            1
        );

        if (!empty($query)) {
            $query = $query[0];

            if (set_sql_query(
                BotDatabaseTable::BOT_TARGETED_MESSAGE_CREATIONS,
                array(
                    "deletion_date" => get_current_date(),
                ),
                array(
                    array("id", $query->id)
                ),
                null,
                1
            )) {
                $channel = $this->plan->bot->discord->getChannel($query->channel_id);

                if ($channel !== null
                    && $channel->allowText()
                    && $channel->guild_id == $query->server_id) {
                    $this->ignoreDeletion++;
                    $channel->guild->channels->delete($channel);
                }
                return true;
            } else {
                global $logger;
                $logger->logError($this->plan->planID, "Failed to close oldest target with ID: " . $query->id);
            }
        }
        return false;
    }
}