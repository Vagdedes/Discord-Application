<?php

use Discord\Builders\Components\ActionRow;
use Discord\Builders\Components\Button;
use Discord\Builders\Components\Option;
use Discord\Builders\Components\SelectMenu;
use Discord\Builders\MessageBuilder;
use Discord\Helpers\Collection;
use Discord\Parts\Channel\Message;
use Discord\Parts\Embed\Embed;
use Discord\Parts\Guild\Guild;
use Discord\Parts\Interactions\Interaction;

class DiscordUserPolls
{
    private DiscordPlan $plan;

    private const
        MANAGE_PERMISSION = "idealistic.user.polls.manage",

        NOT_EXISTS = "This user poll does not exist.",
        NOT_OWNED = "You do not own this user poll.",
        NOT_RUNNING = "This user poll is not currently running.",
        MAX_CHOICES = 25;

    public function __construct(DiscordPlan $plan)
    {
        $this->plan = $plan;
        $this->checkExpired();
        $this->keepAlive();
    }

    public function create(Interaction      $interaction,
                           int|float|string $name,
                           int|float|string $title, int|float|string $description,
                           bool             $allowDeletion,
                           int              $maxChoices,
                           bool             $allowSameChoice): ?MessageBuilder
    {
        $get = $this->getBase($interaction, $name, false);

        if ($get !== null) {
            return MessageBuilder::new()->setContent("This user poll already exists.");
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
            return MessageBuilder::new()->setContent("Failed to insert this user poll into the database.");
        }
    }

    public function delete(Interaction      $interaction,
                           int|float|string $name): ?MessageBuilder
    {
        $get = $this->getBase($interaction, $name);

        if ($get === null) {
            return MessageBuilder::new()->setContent("This user poll does not exist.");
        } else if (!$this->owns($interaction, $get)) {
            return MessageBuilder::new()->setContent(self::NOT_OWNED);
        } else {
            $result = $this->endRaw($get);

            if ($result !== null) {
                return $result;
            } else if (set_sql_query(
                BotDatabaseTable::BOT_POLLS,
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
                    "Failed to delete this user poll from the database."
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
            BotDatabaseTable::BOT_POLLS,
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
                    BotDatabaseTable::BOT_POLL_TRACKING,
                    array(
                        "plan_id" => $this->plan->planID,
                        "poll_id" => $running->poll_id,
                        "poll_creation_id" => $running->poll_creation_id,
                        "server_id" => $running->server_id,
                        "channel_id" => $this->plan->utilities->getChannel($interaction->channel)->id,
                        "thread_id" => $interaction->message->thread?->id,
                        "user_id" => $interaction->member->id,
                        "expiration_date" => $running->expiration_date,
                        "creation_date" => $running->creation_date,
                        "copy" => true
                    )
                )) {
                    $this->update($running->poll_creation_id, $get);
                    return MessageBuilder::new()->setContent("This user poll is already running.");
                } else {
                    return MessageBuilder::new()->setContent(
                        "Failed to copy this user poll into the database."
                    );
                }
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
                        "plan_id" => $this->plan->planID,
                        "poll_id" => $get->id,
                        "poll_creation_id" => $pollCreationID,
                        "server_id" => $interaction->guild_id,
                        "channel_id" => $this->plan->utilities->getChannel($interaction->channel)->id,
                        "thread_id" => $interaction->message->thread?->id,
                        "user_id" => $interaction->member->id,
                        "expiration_date" => get_future_date($duration),
                        "running" => true,
                        "creation_date" => get_current_date()
                    )
                )) {
                    $this->update($pollCreationID, $get);
                    return null;
                } else {
                    return MessageBuilder::new()->setContent(
                        "Failed to insert this user poll into the database."
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
            return MessageBuilder::new()->setContent("This user poll does not exist.");
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
            BotDatabaseTable::BOT_POLL_TRACKING,
            array(
                "deletion_date" => get_current_date(),
                "running" => false
            ),
            array(
                array("poll_creation_id", $query->poll_creation_id)
            ) // Do not limit to 1 iteration as there may be copies
        )) {
            $this->update($query, null, null, true);
            return null;
        } else {
            return MessageBuilder::new()->setContent("Failed to end this user poll from the database.");
        }
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
                array("running", "IS NOT", null),
                array("poll_id", $query->id),
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
                BotDatabaseTable::BOT_POLL_TRACKING,
                null,
                array(
                    array("poll_creation_id", $running)
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
                BotDatabaseTable::BOT_POLLS,
                null,
                array(
                    array("id", $running->poll_id)
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
        $embed->setAuthor("POLL");
        $embed->setTitle($get->title);
        $embed->setDescription($get->description);
        $embed->setFooter($end ? "Expired" : "Last Updated");
        $embed->setTimestamp(time());

        // Separator

        $choices = $this->getChoices($get);
        $allPicks = $this->getPicks(null, $running);
        $allPickCount = sizeof($allPicks);
        $picksMap = array();

        foreach ($allPicks as $pick) {
            if (array_key_exists($pick->choice_id, $picksMap)) {
                $picksMap[$pick->choice_id]++;
            } else {
                $picksMap[$pick->choice_id] = 1;
            }
        }
        arsort($picksMap);
        foreach ($picksMap as $key => $value) {
            $embed->addFieldValues(
                $choices[$key]->name,
                "``" . $value . " Votes | " . round(($value / (double)$allPickCount) * 100, 2) . "%``",
                true
            );
        }

        // Separator

        if ($end) {
            $select = SelectMenu::new()
                ->setMinValues(0)
                ->setMaxValues(0)
                ->setPlaceholder("This poll has expired.");
        } else {
            $select = SelectMenu::new()
                ->setMinValues(1)
                ->setMaxValues(1)
                ->setPlaceholder("Select up to " . $get->max_choices . " vote" . ($get->max_choices == 1 ? "" : "s") . ".");
            $select->setListener(function (Interaction $interaction, Collection $options) use ($running, $get, $choices) {
                $interaction->acknowledge();
                $option = $options[0];
                $choiceID = $option->getValue();
                $actionRow = ActionRow::new();
                $picks = $this->getPicks($interaction, $running);
                $count = array();
                $totalCount = sizeof($picks);

                if ($totalCount > 0) {
                    foreach ($picks as $pick) {
                        if (array_key_exists($pick->choice_id, $count)) {
                            $count[$pick->choice_id]++;
                        } else {
                            $count[$pick->choice_id] = 1;
                        }
                    }
                }
                $button = Button::new(Button::STYLE_PRIMARY)->setLabel(
                    "Pick ("
                    . add_ordinal_number($count[$choiceID] + 1)
                    . " time)"
                );
                $button->setListener(function (Interaction $interaction) use ($running, $option, $get, $choiceID, $choices) {
                    $interaction->acknowledge();
                    $pick = $this->setPick($interaction, $get->name, $choiceID);

                    if ($pick !== null) {
                        $interaction->sendFollowUpMessage($pick, true);
                    } else {
                        $interaction->sendFollowUpMessage(
                            MessageBuilder::new()->setContent("Thanks for voting!"),
                            true
                        );
                    }
                }, $this->plan->bot->discord, true);
                $actionRow->addComponent($button);

                if ($get->allow_choice_deletion !== null && $totalCount > 0) {
                    $button = Button::new(Button::STYLE_DANGER)->setLabel("Delete Picks");
                    $button->setListener(function (Interaction $interaction) use ($get, $choices) {
                        $interaction->acknowledge();
                        $pick = $this->setPick($interaction, $get->name, null, false);

                        if ($pick !== null) {
                            $interaction->sendFollowUpMessage($pick, true);
                        } else {
                            $interaction->sendFollowUpMessage(
                                MessageBuilder::new()->setContent("Your vote has been removed."),
                                true
                            );
                        }
                    }, $this->plan->bot->discord, true);
                    $actionRow->addComponent($button);
                }

                $builder = MessageBuilder::new();
                $embed = new Embed($this->plan->bot->discord);
                $embed->setTitle($get->title);
                $embed->setDescription($get->description);

                if (empty($count)) {
                    $embed->setAuthor("No Votes");
                } else {
                    $embed->setAuthor($totalCount . " Votes");

                    foreach ($count as $key => $value) {
                        $embed->addFieldValues(
                            $choices[$key]->name,
                            "``" . $value . " Votes``"
                        );
                    }
                }
                $builder->addEmbed($embed);
                $builder->addComponent($actionRow);
                $interaction->sendFollowUpMessage(
                    $builder,
                    true
                );
            }, $this->plan->bot->discord);
        }
        foreach ($choices as $choice) {
            $select->addOption(
                Option::new($choice->name, $choice->id)->setDescription($choice->description)
            );
        }
        $builder->addComponent($select);
        $builder->addEmbed($embed);

        if ($running->message_id === null) {
            $channel = $this->plan->bot->discord->getChannel($running->channel_id);

            if ($running->thread_id === null) {
                $channel->sendMessage($builder)->done(function (Message $message) use ($running) {
                    set_sql_query(
                        BotDatabaseTable::BOT_POLL_TRACKING,
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
                                BotDatabaseTable::BOT_POLL_TRACKING,
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

    // Choices

    private function getChoices(object $query, bool $cache = true): array
    {
        if ($cache) {
            set_sql_cache("1 second");
        }
        $array = get_sql_query(
            BotDatabaseTable::BOT_POLL_CHOICES,
            null,
            array(
                array("deletion_date", null),
                array("poll_id", $query->id)
            ),
            null,
            self::MAX_CHOICES
        );

        if (empty($array)) {
            return $array;
        } else {
            $new = array();

            foreach ($array as $choice) {
                $new[$choice->id] = $choice;
            }
            return $new;
        }
    }

    public function setChoice(Interaction      $interaction,
                              int|float|string $name,
                              int|float|string $choiceToAdd, int|string|null $description, bool $set = true): ?MessageBuilder
    {
        $query = $this->getBase($interaction, $name);

        if ($query === null) {
            return MessageBuilder::new()->setContent(self::NOT_EXISTS);
        } else if (!empty($this->getRunning($interaction->guild, $query))) {
            return MessageBuilder::new()->setContent("This user poll is currently running.");
        } else {
            $choices = $this->getChoices($query, false);

            if ($set) {
                $size = sizeof($choices);

                if ($size > 0) {
                    if ($size == self::MAX_CHOICES) {
                        return MessageBuilder::new()->setContent(
                            "This user poll already has the maximum amount of choices."
                        );
                    } else {
                        foreach ($choices as $choice) {
                            if ($choice->name == $choiceToAdd) {
                                return MessageBuilder::new()->setContent(
                                    "This choice is already added to this user poll."
                                );
                            }
                        }
                    }
                }
                if (sql_insert(
                    BotDatabaseTable::BOT_POLL_CHOICES,
                    array(
                        "poll_id" => $query->id,
                        "name" => $choiceToAdd,
                        "description" => $description,
                        "creation_date" => get_current_date(),
                        "created_by" => $interaction->member->id
                    )
                )) {
                    return null;
                } else {
                    return MessageBuilder::new()->setContent(
                        "Failed to insert this choice into the database."
                    );
                }
            } else {
                $notMessage = "This choice is not added to this user poll.";

                if (empty($choices)) {
                    return MessageBuilder::new()->setContent($notMessage);
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
                                return MessageBuilder::new()->setContent(
                                    "Failed to delete this choice from the database."
                                );
                            }
                        }
                    }
                    return MessageBuilder::new()->setContent($notMessage);
                }
            }
        }
    }

    // Choice Picking

    private function getPicks(?Interaction $interaction, object $query, bool $cache = true): array
    {
        if ($cache) {
            set_sql_cache("1 second");
        }
        return get_sql_query(
            BotDatabaseTable::BOT_POLL_CHOICE_TRACKING,
            null,
            array(
                array("deletion_date", null),
                array("poll_creation_id", $query->poll_creation_id),
                $interaction === null ? "" : array("user_id", $interaction->member->id)
            )
        );
    }

    public function setPick(Interaction      $interaction,
                            int|float|string $name, int|string|null $choiceID, bool $set = true): ?MessageBuilder
    {
        $get = $this->getBase($interaction, $name);

        if ($get === null) {
            return MessageBuilder::new()->setContent(self::NOT_EXISTS);
        } else {
            $running = $this->getRunning($interaction->guild, $get);

            if (empty($running)) {
                return MessageBuilder::new()->setContent(self::NOT_RUNNING);
            } else if ($running->expiration_date <= get_current_date()) {
                return $this->endRaw($running);
            } else if ($set) {
                $picks = $this->getPicks($interaction, $running, false);
                $pickCount = sizeof($picks);

                if ($pickCount > 0) {
                    if ($pickCount == $get->max_choices) {
                        return MessageBuilder::new()->setContent(
                            "You have already picked this choice the maximum amount of times."
                        );
                    }
                    foreach ($picks as $pick) {
                        if ($pick->choice_id == $choiceID
                            && $get->allow_same_choice === null) {
                            return MessageBuilder::new()->setContent(
                                "You have already picked this choice."
                            );
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
                    $this->update($running, $get, $interaction->message);
                    return null;
                } else {
                    return MessageBuilder::new()->setContent(
                        "Failed to insert this choice pick into the database."
                    );
                }
            } else if ($get->allow_choice_deletion !== null) {
                $picks = $this->getPicks($interaction, $running, false);
                $notMessage = "You have not picked this choice.";

                if (empty($picks)) {
                    return MessageBuilder::new()->setContent($notMessage);
                } else {
                    $hasChoice = $choiceID !== null;

                    foreach ($picks as $pick) {
                        if (!$hasChoice || $pick->choice_id == $choiceID) {
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
                                $this->update($running, $get, $interaction->message);
                            } else {
                                return MessageBuilder::new()->setContent(
                                    "Failed to delete this choice pick from the database."
                                );
                            }
                        }
                    }
                    return $hasChoice ? MessageBuilder::new()->setContent($notMessage) : null;
                }
            } else {
                return MessageBuilder::new()->setContent("This user poll does not allow choice deletion.");
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
                                "This permission is already required for this user poll."
                            );
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
                    return MessageBuilder::new()->setContent(
                        "Failed to insert this permission into the database."
                    );
                }
            } else {
                $notMessage = "This permission is not required for this user poll.";

                if (empty($permissions)) {
                    return MessageBuilder::new()->setContent($notMessage);
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
                                "This role is already required for this user poll."
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
                    return MessageBuilder::new()->setContent("Failed to insert this role into the database.");
                }
            } else {
                $notMessage = "This role is not required for this user poll.";

                if (empty($roles)) {
                    return MessageBuilder::new()->setContent($notMessage);
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
            BotDatabaseTable::BOT_POLL_TRACKING,
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
            foreach ($query as $poll) {
                $this->update($poll);
            }
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
                array("copy", null),
                array("running", "IS NOT", null),
                array("expiration_date", "<", get_current_date())
            )
        );

        if (!empty($query)) {
            foreach ($query as $poll) {
                $this->endRaw($poll);
            }
        }
    }

}