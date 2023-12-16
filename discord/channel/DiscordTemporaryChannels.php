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
                        $query = $state[1]->inception_channel;
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
                                $outcome = $this->setOwner($update->member, $update->user);

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
            } else if ($this->isBanned($update->channel, $update->user)) {
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
                    $channel->guild->channels->delete($channel);
                } else if (!empty($this->getOwner($channel, $update->user))) {
                    foreach ($members as $member) {
                        if ($member->id != $update->user->id) {
                            $outcome = $this->setOwner($member, $member->user);

                            if ($outcome !== null) {
                                global $logger;
                                $logger->logError($this->plan->planID, $outcome);
                                $this->closeByChannel($channel);
                            }
                            break;
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

    public function setOwner(Member $member, User $user,
                             bool   $set = true, ?string $reason = null): ?string
    {
        $channel = $member->getVoiceChannel();

        if ($channel !== null) {
            $state = $this->getChannelState($channel);

            if (!empty($state)) {
                if ($set) {
                    if ($this->isBanned($channel, $user)) {
                        return "User is banned from this temporary voice channel.";
                    } else if (!empty($this->getOwner($channel, $user))) {
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
                    $owner = $this->getOwner($channel, $user);

                    if (!empty($owner)) {
                        if ($owner[0]->created_by === null) {
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
                                $this->isBanned($channel, $user) ? self::BANNED_MEMBER_PERMISSIONS : array()
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

            if (!empty($state)) {
                if ($set) {
                    if ($this->isBanned($channel, $user)) {
                        return "User is already banned from this temporary voice channel.";
                    } else {
                        $owner = $this->getOwner($channel, $user);

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
                            $string = $this->setOwner($member, $user, false, null);

                            if ($string === null) {
                                $this->kick($user, $channel, $state);
                            }
                            return $string;
                        } else {
                            return "Failed to insert ban into the database.";
                        }
                    }
                } else {
                    if (!$this->isBanned($channel, $user)) {
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

    private function isBanned(Channel $channel, User $user): bool
    {
        set_sql_cache("1 second");
        return !empty(get_sql_query(
            BotDatabaseTable::BOT_TEMPORARY_CHANNEL_BANS,
            null,
            array(
                array("deletion_date", null),
                array("user_id", $user->id),
                array("server_id", $channel->guild_id),
                array("channel_id", $channel->id),
            ),
            array(
                "DESC",
                "id"
            ),
            1
        ));
    }

    // Separator

    public function closeByChannel(Channel         $channel,
                                   int|string|null $userID = null,
                                   ?string         $reason = null): void
    {
        set_sql_cache("1 second");
        $query = get_sql_query(
            BotDatabaseTable::BOT_TEMPORARY_CHANNEL_TRACKING,
            array("id", "deletion_date"),
            array(
                array("created_channel_server_id", $channel->guild_id), //todo fix
                array("created_channel_id", $channel->id),
            ),
            null,
            1
        );

        if (!empty($query)) {
            $query = $query[0];

            if ($query->deletion_date === null) {
                try {
                    if (set_sql_query(
                        BotDatabaseTable::BOT_TEMPORARY_CHANNEL_TRACKING,
                        array(
                            "deletion_date" => get_current_date(),
                            "deletion_reason" => empty($reason) ? null : $reason,
                            "deleted_by" => $userID
                        ),
                        array(
                            array("id", $query->id)
                        ),
                        null,
                        1
                    )) {
                        $this->ignoreDeletion++;
                        $channel->guild->channels->delete(
                            $channel,
                            empty($reason) ? null : $userID . ": " . $reason
                        );
                    }
                } catch (Throwable $exception) {
                    global $logger;
                    $logger->logError($this->plan->planID, $exception->getMessage());
                }
            }
        }
    }

    // Separator

    private function getOwner(Channel $channel, User $user): array
    {
        set_sql_cache("1 second");
        return get_sql_query(
            BotDatabaseTable::BOT_TEMPORARY_CHANNEL_OWNERS,
            null,
            array(
                array("deletion_date", null),
                array("user_id", $user->id),
                array("server_id", $channel->guild_id),
                array("channel_id", $channel->id),
            ),
            array(
                "DESC",
                "id"
            ),
            1
        );
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
}