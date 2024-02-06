<?php

use Discord\Builders\MessageBuilder;
use Discord\Parts\Guild\Guild;
use Discord\Parts\Interactions\Interaction;

class DiscordUserPolls
{
    private DiscordPlan $plan;

    private const
        REFRESH_TIME = "15 seconds",
        MANAGE_PERMISSION = "idealistic.user.polls.manage",

        NOT_EXISTS = "This user poll does not exist.",
        NOT_OWNED = "You do not own this user poll.",
        NOT_RUNNING = "This user poll is not currently running.",
        MAX_CHOICES = 25;

    public function __construct(DiscordPlan $plan)
    {
        $this->plan = $plan;
        $this->checkExpired();
    }

    //todo max 25 choices
    // commands:
    // idealistic-create-poll, idealistic-delete-poll
    // idealistic-start-poll, idealistic-end-poll
    // idealistic-get-poll
    // idealistic-add-poll-choice, idealistic-remove-poll-choice
    // idealistic-add-poll-permission, idealistic-remove-poll-permission
    // idealistic-add-poll-role, idealistic-remove-poll-role

    public function create(Interaction      $interaction,
                           int|float|string $name,
                           int|float|string $title, int|float|string $description,
                           bool             $allowDeletion,
                           bool             $maxChoices,
                           bool             $allowSameChoice): ?string
    {
        $get = $this->getBase($interaction, $name);

        if ($get !== null) {
            return "This user poll already exists.";
        }
        if (sql_insert(
            BotDatabaseTable::BOT_POLLS,
            array(
                "server_id" => $interaction->guild_id,
                "user_id" => $interaction->member->id,
                "name" => $name,
                "title" => $title,
                "description" => $description,
                "allow_choice_deletion" => $allowDeletion,
                "max_choices" => $maxChoices,
                "allow_same_choice" => $allowSameChoice,
                "creation_date" => get_current_date()
            )
        )) {
            return null;
        } else {
            return "Failed to insert this user poll into the database.";
        }
    }

    public function delete(Interaction      $interaction,
                           int|float|string $name): ?string
    {
        $get = $this->getBase($interaction, $name);

        if ($get === null) {
            return "This user poll does not exist.";
        } else if (!$this->owns($interaction, $get)) {
            return self::NOT_OWNED;
        } else {
            $result = $this->endRaw($get);

            if ($result !== null) {
                return $result;
            } else if (set_sql_query(
                BotDatabaseTable::BOT_POLLS,
                array(
                    "deletion_date" => get_current_date()
                ),
                array(
                    array("id", $get->id)
                ),
                null,
                1
            )) {
                return null;
            } else {
                return "Failed to delete this user poll from the database.";
            }
        }
    }

    private function getBase(Interaction $interaction, int|float|string $name): ?object
    {
        $query = get_sql_query(
            BotDatabaseTable::BOT_POLLS,
            array("id"),
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
                          string           $duration,
                          bool             $embed): ?MessageBuilder
    {
        $get = $this->getBase($interaction, $name);

        if ($get === null) {
            return MessageBuilder::new()->setContent(self::NOT_EXISTS);
        } else if (!$this->owns($interaction, $get)) {
            return MessageBuilder::new()->setContent(self::NOT_OWNED);
        } else {
            $running = $this->getRunning($interaction->guild, $get);

            if (!empty($running)) {
                return MessageBuilder::new()->setContent("This user poll is already running.");
            } else if (empty($this->getChoices($get))) {
                return MessageBuilder::new()->setContent("This user poll does not have any choices.");
            }
        }
        while (true) {
            $pollCreationID = random_number(19);

            if (empty(get_sql_query(
                BotDatabaseTable::BOT_POLL_TRACKING,
                array("poll_creation_id"),
                array(
                    array("poll_creation_id", $pollCreationID)
                ),
                null,
                1
            ))) {
                if (sql_insert(
                    BotDatabaseTable::BOT_POLL_TRACKING,
                    array(
                        "poll_id" => $get->id,
                        "poll_creation_id" => $pollCreationID,
                        "server_id" => $interaction->guild_id,
                        "channel_id" => $this->plan->utilities->getChannel($interaction->channel)->id,
                        "thread_id" => $interaction->channel_id,
                        "expiration_date" => get_future_date($duration),
                        "running" => true,
                        "creation_date" => get_current_date()
                    )
                )) {
                    return $this->getMessage($interaction, $name);
                } else {
                    return MessageBuilder::new()->setContent(
                        "Failed to insert this user poll into the database."
                    );
                }
            }
        }
    }

    public function end(Interaction      $interaction,
                        int|float|string $name): ?string
    {
        $get = $this->getBase($interaction, $name);

        if ($get === null) {
            return "This user poll does not exist.";
        } else if (!$this->owns($interaction, $get)) {
            return self::NOT_OWNED;
        } else if (empty($this->getRunning($interaction->guild, $get))) {
            return self::NOT_RUNNING;
        }
        return $this->endRaw($get);
    }

    public function endRaw(object $query): ?string
    {
        //todo
        return null;
    }

    private function getRunning(Guild $guild, object $query): ?object
    {
        $this->checkExpired();
        set_sql_cache("1 second");
        $query = get_sql_query(
            BotDatabaseTable::BOT_POLL_TRACKING,
            null,
            array(
                array("server_id", $guild->id),
                array("deletion_date", null),
                array("running", null),
                array("poll_id", $query->id)
            ),
            null,
            1
        );
        return empty($query) ? null : $query[0];
    }

    private function update(object $query): void
    {
        //todo
    }

    // Choices

    private function getChoices(object $query, bool $cache = true): array
    {
        if ($cache) {
            set_sql_cache("1 second");
        }
        return get_sql_query(
            BotDatabaseTable::BOT_POLL_CHOICES,
            null,
            array(
                array("deletion_date", null),
                array("poll_id", $query->id)
            ),
            null,
            self::MAX_CHOICES
        );
    }

    public function setChoice(Interaction      $interaction,
                              int|float|string $name,
                              int|float|string $choiceToAdd, int|string $description, bool $set = true): ?string
    {
        $query = $this->getBase($interaction, $name);

        if ($query === null) {
            return self::NOT_EXISTS;
        } else if (!empty($this->getRunning($interaction->guild, $query))) {
            return "This user poll is currently running.";
        } else {
            $choices = $this->getChoices($query, false);

            if ($set) {
                $size = sizeof($choices);

                if ($size > 0) {
                    if ($size == self::MAX_CHOICES) {
                        return "This user poll already has the maximum amount of choices.";
                    } else {
                        foreach ($choices as $choice) {
                            if ($choice->name == $choiceToAdd) {
                                return "This choice is already added to this user poll.";
                            }
                        }
                    }
                }
                if (sql_insert(
                    BotDatabaseTable::BOT_POLL_CHOICES,
                    array(
                        "poll_id" => $query->id,
                        "choice" => $choiceToAdd,
                        "description" => $description,
                        "creation_date" => get_current_date(),
                        "created_by" => $interaction->member->id
                    )
                )) {
                    return null;
                } else {
                    return "Failed to insert this choice into the database.";
                }
            } else {
                $notMessage = "This choice is not added to this user poll.";

                if (empty($choices)) {
                    return $notMessage;
                } else {
                    foreach ($choices as $choice) {
                        if ($choice->name == $choiceToAdd) {
                            if (set_sql_query(
                                BotDatabaseTable::BOT_POLL_CHOICES,
                                array(
                                    "deletion_date" => get_current_date(),
                                    "deleted_by" => $interaction->member->id
                                ),
                                array(
                                    array("id", $choice->id)
                                ),
                                null,
                                1
                            )) {
                                return null;
                            } else {
                                return "Failed to delete this choice from the database.";
                            }
                        }
                    }
                    return $notMessage;
                }
            }
        }
    }

    // Choice Picking

    private function getPicks(Interaction $interaction, object $query): array
    {
        return get_sql_query(
            BotDatabaseTable::BOT_POLL_CHOICE_TRACKING,
            null,
            array(
                array("deletion_date", null),
                array("poll_creation_id", $query->poll_creation_id),
                array("user_id", $interaction->member->id)
            )
        );
    }

    public function setPick(Interaction      $interaction,
                            int|float|string $name, int|float|string $choice, bool $set = true): ?string
    {
        $get = $this->getBase($interaction, $name);

        if ($get === null) {
            return self::NOT_EXISTS;
        } else {
            $running = $this->getRunning($interaction->guild, $get);

            if (empty($running)) {
                return self::NOT_RUNNING;
            } else if ($running->expiration_date <= get_current_date()) {
                return $this->endRaw($running);
            } else if ($set) {
                $choiceID = null;

                foreach ($this->getChoices($get, false) as $choiceRow) {
                    if ($choiceRow->choice == $choice) {
                        $choiceID = $choiceRow->id;
                        break;
                    }
                }

                if ($choiceID === null) {
                    return "This user poll does not have this choice.";
                } else {
                    $picks = $this->getPicks($interaction, $running);

                    if (!empty($picks)) {
                        $counter = 0;

                        foreach ($picks as $pick) {
                            if ($pick->choice_id == $choiceID) {
                                if ($get->allow_same_choice === null) {
                                    return "You have already picked this choice.";
                                } else {
                                    $counter++;

                                    if ($counter == $get->max_choices) {
                                        return "You have already picked this choice the maximum amount of times.";
                                    }
                                }
                            }
                        }
                    }
                    if (sql_insert(
                        BotDatabaseTable::BOT_POLL_CHOICE_TRACKING,
                        array(
                            "poll_creation_id" => $running->poll_creation_id,
                            "choice_id" => $choiceID,
                            "user_id" => $interaction->member->id,
                            "creation_date" => get_current_date()
                        )
                    )) {
                        $this->update($running);
                        return null;
                    } else {
                        return "Failed to insert this choice pick into the database.";
                    }
                }
            } else if ($get->allow_choice_deletion !== null) {
                $choiceID = null;

                foreach ($this->getChoices($get, false) as $choiceRow) {
                    if ($choiceRow->choice == $choice) {
                        $choiceID = $choiceRow->id;
                        break;
                    }
                }

                if ($choiceID === null) {
                    return "This user poll does not have this choice.";
                } else {
                    $picks = $this->getPicks($interaction, $running);
                    $notMessage = "You have not picked this choice.";

                    if (empty($picks)) {
                        return $notMessage;
                    } else {
                        foreach ($picks as $pick) {
                            if ($pick->choice_id == $choiceID) {
                                if (set_sql_query(
                                    BotDatabaseTable::BOT_POLL_CHOICE_TRACKING,
                                    array(
                                        "deletion_date" => get_current_date()
                                    ),
                                    array(
                                        array("id", $pick->id)
                                    ),
                                    null,
                                    1
                                )) {
                                    $this->update($running);
                                    return null;
                                } else {
                                    return "Failed to delete this choice pick from the database.";
                                }
                            }
                        }
                        return $notMessage;
                    }
                }
            } else {
                return "This user poll does not allow choice deletion.";
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
            BotDatabaseTable::BOT_POLL_PERMISSIONS,
            null,
            array(
                array("deletion_date", null),
                array("poll_id", $query->id)
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
                                          int|float|string $name, int|string $permissionToAdd, bool $set = true): ?string
    {
        $query = $this->getBase($interaction, $name);

        if ($query === null) {
            return self::NOT_EXISTS;
        } else {
            $permissionToAdd = strtolower($permissionToAdd);
            $permissions = $this->getPermissions($query, false);

            if ($set) {
                if (!empty($permissions)) {
                    foreach ($permissions as $permission) {
                        if ($permission->permission == $permissionToAdd) {
                            return "This permission is already required for this user poll.";
                        }
                    }
                }
                if (sql_insert(
                    BotDatabaseTable::BOT_POLL_PERMISSIONS,
                    array(
                        "poll_id" => $query->id,
                        "permission" => $permissionToAdd,
                        "creation_date" => get_current_date(),
                        "created_by" => $interaction->member->id
                    )
                )) {
                    return null;
                } else {
                    return "Failed to insert this permission into the database.";
                }
            } else {
                $notMessage = "This permission is not required for this user poll.";

                if (empty($permissions)) {
                    return $notMessage;
                } else {
                    foreach ($permissions as $permission) {
                        if ($permission->permission == $permissionToAdd) {
                            if (set_sql_query(
                                BotDatabaseTable::BOT_POLL_PERMISSIONS,
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
                                return "Failed to delete this permission from the database.";
                            }
                        }
                    }
                    return $notMessage;
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
            BotDatabaseTable::BOT_POLL_ROLES,
            null,
            array(
                array("deletion_date", null),
                array("poll_id", $query->id)
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
                                    int|float|string $name, int|string $roleID, bool $set = true): ?string
    {
        $query = $this->getBase($interaction, $name);

        if ($query === null) {
            return self::NOT_EXISTS;
        } else {
            $roles = $this->getRequiredRoles($query, false);

            if ($set) {
                if (!empty($roles)) {
                    foreach ($roles as $role) {
                        if ($role->role_id == $roleID) {
                            return "This role is already required for this user poll.";
                        }
                    }
                }
                $serverRoles = $interaction->guild->roles->toArray();
                $notMessage = "This role does not exist in this server.";

                if (empty($serverRoles)) {
                    return $notMessage;
                } else {
                    $continue = false;

                    foreach ($serverRoles as $serverRole) {
                        if ($serverRole->id == $roleID) {
                            $continue = true;
                            break;
                        }
                    }

                    if (!$continue) {
                        return $notMessage;
                    }
                }
                if (sql_insert(
                    BotDatabaseTable::BOT_POLL_ROLES,
                    array(
                        "poll_id" => $query->id,
                        "role_id" => $roleID,
                        "creation_date" => get_current_date(),
                        "created_by" => $interaction->member->id
                    )
                )) {
                    return null;
                } else {
                    return "Failed to insert this role into the database.";
                }
            } else {
                $notMessage = "This role is not required for this user poll.";

                if (empty($roles)) {
                    return $notMessage;
                } else {
                    foreach ($roles as $role) {
                        if ($role->role_id == $roleID) {
                            if (set_sql_query(
                                BotDatabaseTable::BOT_POLL_ROLES,
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
                                return "Failed to delete this role from the database.";
                            }
                        }
                    }
                    return $notMessage;
                }
            }
        }
    }

    // Utilities

    public function getMessage(Interaction $interaction, int|float|string $name): MessageBuilder
    {
        $query = $this->getBase($interaction, $name);

        if ($query === null) {
            return MessageBuilder::new()->setContent(self::NOT_EXISTS);
        } else {
            $builder = MessageBuilder::new();
            //todo
            return $builder;
        }
    }

    private function checkExpired(): void
    {
        $query = get_sql_query(
            BotDatabaseTable::BOT_POLL_TRACKING,
            null,
            array(
                array("plan_id", $this->plan->planID),
                array("deletion_date", null),
                array("running", null),
                array("expiration_date", "<", get_current_date())
            )
        );

        if (!empty($query)) {
            foreach ($query as $poll) {
                $this->endRaw($poll);
            }
        }
    }

    private function owns(Interaction $interaction, object $query): bool
    {
        set_sql_cache("1 second");
        return $query->user_id == $interaction->member->id
            || $this->plan->permissions->hasPermission($interaction->member, self::MANAGE_PERMISSION);
    }
}