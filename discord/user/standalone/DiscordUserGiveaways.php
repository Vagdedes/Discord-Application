<?php

use Discord\Builders\MessageBuilder;
use Discord\Parts\Channel\Message;
use Discord\Parts\Embed\Embed;
use Discord\Parts\Guild\Guild;
use Discord\Parts\Interactions\Interaction;

class DiscordUserGiveaways
{
    private DiscordPlan $plan;

    private const
        MANAGE_PERMISSION = "idealistic.user.giveaway.manage",

        NOT_EXISTS = "This user giveaway does not exist.",
        NOT_OWNED = "You do not own this user giveaway.",
        NOT_RUNNING = "This user giveaway is not currently running.";

    public function __construct(DiscordPlan $plan)
    {
        $this->plan = $plan;
        $this->checkExpired();
        $this->keepAlive();
    }

    public function create(Interaction      $interaction,
                           int|float|string $name,
                           int|float|string $title, int|float|string $description,
                           int              $minParticipants,
                           int              $maxParticipants,
                           int              $winnerAmount,
                           bool             $repeatAfterEnding): ?MessageBuilder
    {
        $get = $this->getBase($interaction, $name, false);

        if ($get !== null) {
            return MessageBuilder::new()->setContent("This user giveaway already exists.");
        } else if (sql_insert(
            BotDatabaseTable::BOT_GIVEAWAYS,
            array(
                "server_id" => $interaction->guild_id,
                "user_id" => $interaction->member->id,
                "name" => $name,
                "title" => $title,
                "description" => $description,
                "duration" => $duration,
                "min_participants" => $minParticipants,
                "max_participants" => $maxParticipants,
                "winner_amount" => $winnerAmount,
                "repeat_after_ending" => $repeatAfterEnding,
                "creation_date" => get_current_date()
            )
        )) {
            return null;
        } else {
            return MessageBuilder::new()->setContent("Failed to insert this user giveaway into the database.");
        }
    }

    public function delete(Interaction      $interaction,
                           int|float|string $name): ?MessageBuilder
    {
        $get = $this->getBase($interaction, $name);

        if ($get === null) {
            return MessageBuilder::new()->setContent("This user giveaway does not exist.");
        } else if (!$this->owns($interaction, $get)) {
            return MessageBuilder::new()->setContent(self::NOT_OWNED);
        } else {
            $result = $this->endRaw($get);

            if ($result !== null) {
                return $result;
            } else if (set_sql_query(
                BotDatabaseTable::BOT_GIVEAWAYS,
                array(
                    "deletion_date" => get_current_date(),
                    "deleted_by" => $interaction->member->id
                ),
                array(
                    array("id", $get->id)
                ),
                null,
                1
            )) {
                return null;
            } else {
                return MessageBuilder::new()->setContent(
                    "Failed to delete this user giveaway from the database."
                );
            }
        }
    }

    private function getBase(Interaction $interaction, int|float|string $name, bool $cache = true): ?object
    {
        if ($cache) {
            set_sql_cache("1 second");
        }
        $query = get_sql_query(
            BotDatabaseTable::BOT_GIVEAWAYS,
            null,
            array(
                array("server_id", $interaction->guild_id),
                array("deletion_date", null),
                array("name", $name),
            ),
            null,
            1
        );
        return empty($query) ? null : $query[0];
    }

    // Separator

    public function start(Interaction      $interaction,
                          int|float|string $name,
                          string           $duration): ?MessageBuilder
    {
        $get = $this->getBase($interaction, $name);

        if ($get === null) {
            return MessageBuilder::new()->setContent(self::NOT_EXISTS);
        } else if (!$this->owns($interaction, $get)) {
            return MessageBuilder::new()->setContent(self::NOT_OWNED);
        } else if (!is_valid_text_time($duration)) {
            return MessageBuilder::new()->setContent("Invalid duration format.");
        } else {
            $running = $this->getRunning($interaction->guild, $get);

            if (!empty($running)) {
                if (sql_insert(
                    BotDatabaseTable::BOT_GIVEAWAY_TRACKING,
                    array(
                        "plan_id" => $this->plan->planID,
                        "giveaway_id" => $running->giveaway_id,
                        "giveaway_creation_id" => $running->giveaway_creation_id,
                        "server_id" => $running->server_id,
                        "channel_id" => $this->plan->utilities->getChannel($interaction->channel)->id,
                        "thread_id" => $interaction->message->thread?->id,
                        "user_id" => $interaction->member->id,
                        "expiration_date" => $running->expiration_date,
                        "creation_date" => $running->creation_date,
                        "copy" => true
                    )
                )) {
                    $this->update($running->giveaway_creation_id, $get);
                    return MessageBuilder::new()->setContent("This user giveaway is already running.");
                } else {
                    return MessageBuilder::new()->setContent(
                        "Failed to copy this user giveaway into the database."
                    );
                }
            }
        }
        while (true) {
            $giveawayCreationID = random_number(19);

            if (empty(get_sql_query(
                BotDatabaseTable::BOT_GIVEAWAY_TRACKING,
                array("giveaway_creation_id"),
                array(
                    array("giveaway_creation_id", $giveawayCreationID)
                ),
                null,
                1
            ))) {
                if (sql_insert(
                    BotDatabaseTable::BOT_GIVEAWAY_TRACKING,
                    array(
                        "plan_id" => $this->plan->planID,
                        "giveaway_id" => $get->id,
                        "giveaway_creation_id" => $giveawayCreationID,
                        "server_id" => $interaction->guild_id,
                        "channel_id" => $this->plan->utilities->getChannel($interaction->channel)->id,
                        "thread_id" => $interaction->message->thread?->id,
                        "user_id" => $interaction->member->id,
                        "expiration_date" => get_future_date($duration),
                        "running" => true,
                        "creation_date" => get_current_date()
                    )
                )) {
                    $this->update($giveawayCreationID, $get);
                    return null;
                } else {
                    return MessageBuilder::new()->setContent(
                        "Failed to insert this user giveaway into the database."
                    );
                }
            }
        }
    }

    public function end(Interaction      $interaction,
                        int|float|string $name): ?MessageBuilder
    {
        $get = $this->getBase($interaction, $name);

        if ($get === null) {
            return MessageBuilder::new()->setContent("This user giveaway does not exist.");
        } else if (!$this->owns($interaction, $get)) {
            return MessageBuilder::new()->setContent(self::NOT_OWNED);
        } else {
            $running = $this->getRunning($interaction->guild, $get);

            if (empty($running)) {
                return MessageBuilder::new()->setContent(self::NOT_RUNNING);
            }
        }
        return $this->endRaw($running);
    }

    public function endRaw(object $query): ?MessageBuilder
    {
        if (set_sql_query(
            BotDatabaseTable::BOT_GIVEAWAY_TRACKING,
            array(
                "deletion_date" => get_current_date(),
                "running" => false
            ),
            array(
                array("giveaway_creation_id", $query->giveaway_creation_id)
            ) // Do not limit to 1 iteration as there may be copies
        )) {
            $this->update($query, null, null, true);
            return null;
        } else {
            return MessageBuilder::new()->setContent("Failed to end this user giveaway from the database.");
        }
    }

    private function getRunning(Guild $guild, object $query): ?object
    {
        $this->checkExpired();
        set_sql_cache("1 second");
        $query = get_sql_query(
            BotDatabaseTable::BOT_GIVEAWAY_TRACKING,
            null,
            array(
                array("server_id", $guild->id),
                array("deletion_date", null),
                array("running", "IS NOT", null),
                array("giveaway_id", $query->id),
                array("copy", null)
            ),
            null,
            1
        );
        return empty($query) ? null : $query[0];
    }

    private function update(object|int|string $running,
                            object            $get = null,
                            ?Message          $message = null,
                            bool              $end = false): void
    {
        if (is_numeric($running)) {
            $running = get_sql_query(
                BotDatabaseTable::BOT_GIVEAWAY_TRACKING,
                null,
                array(
                    array("giveaway_creation_id", $running)
                ),
                array(
                    "DESC",
                    "id"
                ),
                1
            );

            if (!empty($running)) {
                $running = $running[0];
            } else {
                return;
            }
        }
        if ($get === null) {
            $get = get_sql_query(
                BotDatabaseTable::BOT_GIVEAWAYS,
                null,
                array(
                    array("id", $running->giveaway_id)
                ),
                null,
                1
            );

            if (!empty($get)) {
                $get = $get[0];
            } else {
                return;
            }
        }
        $builder = MessageBuilder::new();
        $embed = new Embed($this->plan->bot->discord);
        $embed->setAuthor("GIVEAWAY");
        $embed->setTitle($get->title);
        $embed->setDescription($get->description);
        $embed->setFooter($end ? "Expired" : "Last Updated");
        $embed->setTimestamp(time());

        // Separator

        // todo

        if ($end) {

        } else {

        }
        $builder->addEmbed($embed);

        if ($running->message_id === null) {
            $channel = $this->plan->bot->discord->getChannel($running->channel_id);

            if ($running->thread_id === null) {
                $channel->sendMessage($builder)->done(function (Message $message) use ($running) {
                    set_sql_query(
                        BotDatabaseTable::BOT_GIVEAWAY_TRACKING,
                        array(
                            "message_id" => $message->id
                        ),
                        array(
                            array("id", $running->id)
                        ),
                        null,
                        1
                    );
                });
            } else if (!empty($channel->threads->first())) {
                foreach ($channel->threads as $thread) {
                    if ($thread->id == $running->thread_id) {
                        $thread->sendMessage($builder)->done(function (Message $message) use ($running) {
                            set_sql_query(
                                BotDatabaseTable::BOT_GIVEAWAY_TRACKING,
                                array(
                                    "message_id" => $message->id
                                ),
                                array(
                                    array("id", $running->id)
                                ),
                                null,
                                1
                            );
                        });
                        break;
                    }
                }
            }
        } else if ($message !== null) {
            $message->edit($builder);
        } else {
            $channel = $this->plan->bot->discord->getChannel($running->channel_id);

            if ($running->thread_id !== null) {
                if (!empty($channel->threads->first())) {
                    foreach ($channel->threads as $thread) {
                        if ($thread->id == $running->thread_id) {
                            $channel = $thread;
                            break;
                        }
                    }
                }
            }
            try {
                $channel->messages->fetch($running->message_id)->done(function (Message $message) use ($builder) {
                    $message->edit($builder);
                });
            } catch (Throwable $ignored) {
            }
        }
    }

    // Permissions

    private function getPermissions(object $query, bool $cache = true): array
    {
        if ($cache) {
            set_sql_cache("1 second");
        }
        return get_sql_query(
            BotDatabaseTable::BOT_GIVEAWAYS_PERMISSIONS,
            null,
            array(
                array("deletion_date", null),
                array("giveaway_id", $query->id)
            )
        );
    }

    private function hasPermission(Interaction $interaction, object $query): bool
    {
        $permissions = $this->getPermissions($query);

        if (!empty($permissions)) {
            foreach ($permissions as $permission) {
                if (!$this->plan->permissions->hasPermission($interaction->member, $permission->permission_id)) {
                    return false;
                }
            }
        }
        return true;
    }

    public function setRequiredPermission(Interaction      $interaction,
                                          int|float|string $name,
                                          int|string       $permissionToAdd,
                                          bool             $set = true): ?MessageBuilder
    {
        $query = $this->getBase($interaction, $name);

        if ($query === null) {
            return MessageBuilder::new()->setContent(self::NOT_EXISTS);
        } else {
            $permissionToAdd = strtolower($permissionToAdd);
            $permissions = $this->getPermissions($query, false);

            if ($set) {
                if (!empty($permissions)) {
                    foreach ($permissions as $permission) {
                        if ($permission->permission == $permissionToAdd) {
                            return MessageBuilder::new()->setContent(
                                "This permission is already required for this user giveaway."
                            );
                        }
                    }
                }
                if (sql_insert(
                    BotDatabaseTable::BOT_GIVEAWAYS_PERMISSIONS,
                    array(
                        "giveaway_id" => $query->id,
                        "permission" => $permissionToAdd,
                        "creation_date" => get_current_date(),
                        "created_by" => $interaction->member->id
                    )
                )) {
                    return null;
                } else {
                    return MessageBuilder::new()->setContent(
                        "Failed to insert this permission into the database."
                    );
                }
            } else {
                $notMessage = "This permission is not required for this user giveaway.";

                if (empty($permissions)) {
                    return MessageBuilder::new()->setContent($notMessage);
                } else {
                    foreach ($permissions as $permission) {
                        if ($permission->permission == $permissionToAdd) {
                            if (set_sql_query(
                                BotDatabaseTable::BOT_GIVEAWAYS_PERMISSIONS,
                                array(
                                    "deletion_date" => get_current_date(),
                                    "deleted_by" => $interaction->member->id
                                ),
                                array(
                                    array("id", $permission->id)
                                ),
                                null,
                                1
                            )) {
                                return null;
                            } else {
                                return MessageBuilder::new()->setContent(
                                    "Failed to delete this permission from the database."
                                );
                            }
                        }
                    }
                    return MessageBuilder::new()->setContent($notMessage);
                }
            }
        }
    }

    // Roles

    private function getRequiredRoles(object $query, bool $cache = true): array
    {
        if ($cache) {
            set_sql_cache("1 second");
        }
        return get_sql_query(
            BotDatabaseTable::BOT_GIVEAWAYS_ROLES,
            null,
            array(
                array("deletion_date", null),
                array("giveaway_id", $query->id)
            )
        );
    }

    private function hasRequiredRole(Interaction $interaction, object $query): bool
    {
        $roles = $this->getRequiredRoles($query);

        if (!empty($roles)) {
            $memberRoles = $interaction->member->roles->toArray();

            if (empty($memberRoles)) {
                return false;
            }
            foreach ($roles as $role) {
                $has = false;

                foreach ($memberRoles as $memberRole) {
                    if ($role->role_id == $memberRole->id) {
                        $has = true;
                        break;
                    }
                }
                if (!$has) {
                    return false;
                }
            }
        }
        return false;
    }

    public function setRequiredRole(Interaction      $interaction,
                                    int|float|string $name, int|string $roleID, bool $set = true): ?MessageBuilder
    {
        $query = $this->getBase($interaction, $name);

        if ($query === null) {
            return MessageBuilder::new()->setContent(self::NOT_EXISTS);
        } else {
            $roles = $this->getRequiredRoles($query, false);

            if ($set) {
                if (!empty($roles)) {
                    foreach ($roles as $role) {
                        if ($role->role_id == $roleID) {
                            return MessageBuilder::new()->setContent(
                                "This role is already required for this user giveaway."
                            );
                        }
                    }
                }
                $notMessage = "This role does not exist in this server.";

                if (empty($interaction->guild->roles->first())) {
                    return MessageBuilder::new()->setContent($notMessage);
                } else {
                    $continue = false;

                    foreach ($interaction->guild->roles as $serverRole) {
                        if ($serverRole->id == $roleID) {
                            $continue = true;
                            break;
                        }
                    }

                    if (!$continue) {
                        return MessageBuilder::new()->setContent($notMessage);
                    }
                }
                if (sql_insert(
                    BotDatabaseTable::BOT_GIVEAWAYS_ROLES,
                    array(
                        "giveaway_id" => $query->id,
                        "role_id" => $roleID,
                        "creation_date" => get_current_date(),
                        "created_by" => $interaction->member->id
                    )
                )) {
                    return null;
                } else {
                    return MessageBuilder::new()->setContent("Failed to insert this role into the database.");
                }
            } else {
                $notMessage = "This role is not required for this user giveaway.";

                if (empty($roles)) {
                    return MessageBuilder::new()->setContent($notMessage);
                } else {
                    foreach ($roles as $role) {
                        if ($role->role_id == $roleID) {
                            if (set_sql_query(
                                BotDatabaseTable::BOT_GIVEAWAYS_ROLES,
                                array(
                                    "deletion_date" => get_current_date(),
                                    "deleted_by" => $interaction->member->id
                                ),
                                array(
                                    array("id", $role->id)
                                ),
                                null,
                                1
                            )) {
                                return null;
                            } else {
                                return MessageBuilder::new()->setContent(
                                    "Failed to delete this role from the database."
                                );
                            }
                        }
                    }
                    return MessageBuilder::new()->setContent($notMessage);
                }
            }
        }
    }

    // Utilities

    private function owns(Interaction $interaction, object $query): bool
    {
        return $query->user_id == $interaction->member->id
            || $this->plan->permissions->hasPermission($interaction->member, self::MANAGE_PERMISSION);
    }

    // Maintenance

    private function keepAlive(): void
    {
        $query = get_sql_query(
            BotDatabaseTable::BOT_GIVEAWAY_TRACKING,
            null,
            array(
                array("plan_id", $this->plan->planID),
                array("deletion_date", null),
                array("expiration_date", ">=", get_current_date()),
                null,
                array("running", "IS NOT", null, 0),
                array("copy", "IS NOT", null, 0),
                null
            ),
            array(
                "DESC",
                "id"
            )
        );

        if (!empty($query)) {
            foreach ($query as $giveaway) {
                $this->update($giveaway);
            }
        }
    }

    private function checkExpired(): void
    {
        $query = get_sql_query(
            BotDatabaseTable::BOT_GIVEAWAY_TRACKING,
            null,
            array(
                array("plan_id", $this->plan->planID),
                array("deletion_date", null),
                array("copy", null),
                array("running", "IS NOT", null),
                array("expiration_date", "<", get_current_date())
            )
        );

        if (!empty($query)) {
            foreach ($query as $giveaway) {
                $this->endRaw($giveaway);
            }
        }
    }
}