<?php

use Discord\Parts\Channel\Channel;
use Discord\Parts\User\Member;
use Discord\Parts\User\User;
use Discord\Parts\WebSockets\VoiceStateUpdate;

class DiscordTemporaryChannels
{
    private DiscordBot $bot;
    private array $channels;
    public int $ignoreDeletion, $ignoreLeave;

    private const
        NOT_IN_CHANNEL = "You are not in a voice channel.",
        NOT_IN_TEMPORARY_CHANNEL = "You are not in a temporary voice channel.",
        OWNER_PERMISSIONS = array(
        "manage_channels"
    ),
        BANNED_MEMBER_PERMISSIONS = array(
        "connect",
        "speak"
    ),
        LOCKED_MEMBER_PERMISSIONS = array(
        "connect"
    );

    public function __construct(DiscordBot $bot)
    {
        $this->ignoreDeletion = 0;
        $this->ignoreLeave = 0;
        $this->bot = $bot;
        $this->channels = get_sql_query(
            BotDatabaseTable::BOT_TEMPORARY_CHANNELS,
            null,
            array(
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
                $this->channels[$row->inception_channel_id] = $row;
            }
        }
        $this->checkExpired();
    }

    public function trackJoin(VoiceStateUpdate $update): bool
    {
        if ($update->member->id != $this->bot->botID) {
            $state = $this->getChannelState($update->channel);

            if ($state !== null) {
                if ($state[0]) {
                    $query = $state[1];

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
                                $this->bot->utilities->createChannel(
                                    $update->guild,
                                    Channel::TYPE_VOICE,
                                    $query->inception_channel_category_id,
                                    ($query->inception_channel_prefix ?? "") . $update->member->username . ($query->inception_channel_suffix ?? ""),
                                    $query->inception_channel_topic,
                                    $rolePermissions,
                                )?->done(function (Channel $channel) use ($update, $query, $temporaryID, $date) {
                                    if (sql_insert(
                                        BotDatabaseTable::BOT_TEMPORARY_CHANNEL_TRACKING,
                                        array(
                                            "temporary_channel_id" => $query->id,
                                            "temporary_channel_creation_id" => $temporaryID,
                                            "server_id" => $channel->guild_id,
                                            "channel_id" => $channel->id,
                                            "creation_date" => $date,
                                            "expiration_date" => $query->duration !== null ? get_future_date($query->duration) : null,
                                        )
                                    )) {
                                        $update->member->moveMember($channel)->done(function () use ($update, $channel) {
                                            $outcome = $this->setOwner($update->member, $update->user, true, null, $channel, true);

                                            if ($outcome !== null) {
                                                global $logger;
                                                $logger->logError($outcome);
                                                $this->closeByChannel($channel);
                                            }
                                        });
                                    } else {
                                        global $logger;
                                        $logger->logError(
                                            "Failed to insert temporary channel into the database with ID: " . $query->id
                                        );
                                        $this->closeByChannel($channel);
                                    }
                                });
                                break;
                            }
                        }
                    } else {
                        $update->member->moveMember(null);
                    }
                } else if ($this->isBanned($update->user, $state[1]->temporary_channel_creation_id)) {
                    $this->kick($update->member, $update->channel);
                }
                $this->checkExpired();
                return true;
            }
        }
        return false;
    }

    public function trackLeave(VoiceStateUpdate $update): bool
    {
        $channel = $update->channel;
        $state = $this->getChannelState($channel);

        if ($state !== null) {
            if (!$state[0]) {
                $members = $channel->members->toArray();
                unset($members[$update->member->id]);

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
                    if ($this->ignoreLeave === 0) {
                        $this->ignoreDeletion++;
                        $update->guild->channels->delete($channel);
                    } else {
                        $this->ignoreLeave--;
                    }
                } else if ($state[1]->inception_channel->remove_owner_on_leave !== null) {
                    $owner = $this->getOwner($update->user, $state[1]->temporary_channel_creation_id);

                    if (!empty($owner) && $owner[0]->created_by === null) {
                        $this->setOwner($update->member, $update->user, false, null, $channel, true);

                        if ($this->getOwners($state[1]->temporary_channel_creation_id) === 0) {
                            $member = array_shift($members);
                            $outcome = $this->setOwner($member, $member->user, true, null, $channel, true);

                            if ($outcome !== null) {
                                global $logger;
                                $logger->logError($outcome);
                                $this->closeByChannel($channel);
                            }
                        }
                    }
                }
                $this->checkExpired();
                return true;
            } else {
                $this->checkExpired();
            }
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

        if ($channel instanceof Channel) {
            $state = $this->getChannelState($channel);

            if ($state !== null && !$state[0]) {
                if ($set) {
                    $owner = $this->getOwner($member, $state[1]->temporary_channel_creation_id);

                    if (!$force && empty($owner)) {
                        return "You cannot add owners to this temporary channel.";
                    } else if ($this->isBanned($user, $state[1]->temporary_channel_creation_id)) {
                        return "User is banned from this temporary voice channel.";
                    } else if (!$force && !empty($this->getOwner($user, $state[1]->temporary_channel_creation_id))) {
                        return "User is already an owner of this temporary voice channel.";
                    } else {
                        if ($force) {
                            $findMember = $member;
                        } else {
                            $members = $channel->members->toArray();
                            $findMember = $members[$user->id] ?? null;
                        }

                        if ($findMember !== null) {
                            if (sql_insert(
                                BotDatabaseTable::BOT_TEMPORARY_CHANNEL_OWNERS,
                                array(
                                    "user_id" => $user->id,
                                    "temporary_channel_id" => $state[1]->temporary_channel_id,
                                    "temporary_channel_creation_id" => $state[1]->temporary_channel_creation_id,
                                    "creation_date" => get_current_date(),
                                    "creation_reason" => $reason,
                                    "created_by" => $findMember->id == $user->id ? null : $findMember->id,
                                )
                            )) {
                                if ($state[1]->inception_channel->owner_can_manage !== null) {
                                    $channel->setPermissions(
                                        $findMember,
                                        self::OWNER_PERMISSIONS,
                                    );
                                }
                                return null;
                            } else {
                                return "Failed to insert owner into the database.";
                            }
                        } else {
                            return "Could not find this user in this temporary voice channel.";
                        }
                    }
                } else if ($member->id == $user->id) {
                    return "You cannot remove yourself from an owner of this temporary voice channel.";
                } else {
                    $owner = $this->getOwner($member, $state[1]->temporary_channel_creation_id);

                    if (empty($owner)) {
                        return "You cannot remove owners this temporary channel.";
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
                                    array("temporary_channel_creation_id", $state[1]->temporary_channel_creation_id)
                                ),
                                null,
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
                }
            } else {
                return self::NOT_IN_TEMPORARY_CHANNEL;
            }
        } else {
            return self::NOT_IN_CHANNEL;
        }
    }

    // Separator

    private function isLocked(int|string $temporaryID): bool
    {
        $query = get_sql_query(
            BotDatabaseTable::BOT_TEMPORARY_CHANNEL_TRACKING,
            array("lock_date"),
            array(
                array("temporary_channel_creation_id", $temporaryID)
            ),
            null,
            1
        );
        return !empty($query) && $query[0]->lock_date !== null;
    }

    public function setLock(Member $member, bool $set = true): ?string
    {
        $channel = $member->getVoiceChannel();

        if ($channel instanceof Channel) {
            $state = $this->getChannelState($channel);

            if ($state !== null && !$state[0]) {
                if ($set) {
                    if ($this->isLocked($state[1]->temporary_channel_creation_id)) {
                        return "This temporary channel is already locked.";
                    } else {
                        $owner = $this->getOwner($member, $state[1]->temporary_channel_creation_id);

                        if (empty($owner)) {
                            return "You cannot lock this temporary channel.";
                        } else if (set_sql_query(
                            BotDatabaseTable::BOT_TEMPORARY_CHANNEL_TRACKING,
                            array(
                                "lock_date" => get_current_date(),
                            ),
                            array(
                                array("temporary_channel_creation_id", $state[1]->temporary_channel_creation_id)
                            ),
                            null,
                            1
                        )) {
                            $channel->setPermissions(
                                $channel->guild->roles->toArray()[$channel->guild_id],
                                array(),
                                self::LOCKED_MEMBER_PERMISSIONS
                            );
                            return null;
                        } else {
                            return "Failed to set lock in the database.";
                        }
                    }
                } else {
                    $owner = $this->getOwner($member, $state[1]->temporary_channel_creation_id);

                    if (empty($owner)) {
                        return "You cannot unlock this temporary channel.";
                    } else if (!$this->isLocked($state[1]->temporary_channel_creation_id)) {
                        return "This temporary channel is not locked.";
                    } else if (set_sql_query(
                        BotDatabaseTable::BOT_TEMPORARY_CHANNEL_TRACKING,
                        array(
                            "lock_date" => null,
                        ),
                        array(
                            array("temporary_channel_creation_id", $state[1]->temporary_channel_creation_id)
                        ),
                        null,
                        1
                    )) {
                        $channel->setPermissions($channel->guild->roles->toArray()[$channel->guild_id]);
                        return null;
                    } else {
                        return "Failed to set unlock in the database.";
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

        if ($channel instanceof Channel) {
            $state = $this->getChannelState($channel);

            if ($state !== null && !$state[0]) {
                if ($set) {
                    if ($this->isBanned($user, $state[1]->temporary_channel_creation_id)) {
                        $this->kick($this->bot->utilities->getMember($channel->guild, $user), $channel);
                        return "User is already banned from this temporary voice channel.";
                    } else {
                        $owner = $this->getOwner($member, $state[1]->temporary_channel_creation_id);

                        if (empty($owner)) {
                            return "You cannot ban in this temporary channel.";
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
                                    "creation_date" => get_current_date(),
                                    "creation_reason" => $reason,
                                    "created_by" => $member->id,
                                )
                            )) {
                                $this->setOwner($member, $user, false);
                                $this->kick($this->bot->utilities->getMember($channel->guild, $user), $channel);
                                return null;
                            } else {
                                return "Failed to insert ban into the database.";
                            }
                        }
                    }
                } else {
                    $owner = $this->getOwner($member, $state[1]->temporary_channel_creation_id);

                    if (empty($owner)) {
                        return "You cannot unban in this temporary channel.";
                    } else if (!$this->isBanned($user, $state[1]->temporary_channel_creation_id)) {
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
                            array("temporary_channel_creation_id", $state[1]->temporary_channel_creation_id)
                        ),
                        null,
                        1
                    )) {
                        $member = $this->bot->utilities->getMember($channel->guild, $user);

                        if ($member !== null) {
                            $channel->setPermissions($member);
                            return null;
                        } else {
                            return "Failed to find member in this Discord server.";
                        }
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
        return !empty(get_sql_query(
            BotDatabaseTable::BOT_TEMPORARY_CHANNEL_BANS,
            null,
            array(
                array("deletion_date", null),
                array("user_id", $user->id),
                array("temporary_channel_creation_id", $temporaryID)
            ),
            null,
            1
        ));
    }

    // Separator

    public function closeByChannel(Channel $channel, bool $delete = true): void
    {
        $state = $this->getChannelState($channel);

        if ($state !== null && !$state[0]) {
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
                    if ($delete) {
                        $this->ignoreDeletion++;
                        $channel->guild->channels->delete($channel);
                    } else {
                        $this->ignoreLeave++;
                    }
                }
            } catch (Throwable $exception) {
                global $logger;
                $logger->logError($exception->getMessage());
            }
        }
    }

    // Separator

    private function getOwner(Member|User $user, int|string $temporaryID): array
    {
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
        return sizeof(get_sql_query(
            BotDatabaseTable::BOT_TEMPORARY_CHANNEL_OWNERS,
            null,
            array(
                array("deletion_date", null),
                array("temporary_channel_id", $temporaryID)
            )
        ));
    }

    // Separator

    private function getChannelState(Channel $channel): ?array
    {
        $channelInitiator = $this->channels[$channel->id] ?? null;

        if ($channelInitiator !== null) {
            if ($channel->guild_id == $channelInitiator->inception_server_id
                && $channel->allowVoice()) {
                return array(true, $channelInitiator);
            }
        } else {
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

    private function kick(?Member $member, Channel $channel): void
    {
        if ($member !== null) {
            $channel->setPermissions(
                $member,
                array(),
                self::BANNED_MEMBER_PERMISSIONS
            );
            if (array_key_exists($member->id, $channel->members->toArray())) {
                $member->moveMember(null);
            }
        }
    }

    // Separator

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
                    $channel = $this->bot->discord->getChannel($row->channel_id);

                    if ($channel !== null
                        && $channel->allowVoice()
                        && $channel->guild_id == $row->server_id) {
                        $this->ignoreDeletion++;
                        $channel->guild->channels->delete($channel);
                    }
                } else {
                    global $logger;
                    $logger->logError(
                        "Failed to close expired temporary channel with ID: " . $row->id
                    );
                }
            }
        }
    }

    private function hasMaxOpen(int|string $temporaryID, int|string $limit): bool
    {
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
                null,
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
                $channel = $this->bot->discord->getChannel($query->channel_id);

                if ($channel !== null
                    && $channel->allowVoice()
                    && $channel->guild_id == $query->server_id) {
                    $this->ignoreDeletion++;
                    $channel->guild->channels->delete($channel);
                }
                return true;
            } else {
                global $logger;
                $logger->logError("Failed to close oldest target with ID: " . $query->id);
            }
        }
        return false;
    }
}