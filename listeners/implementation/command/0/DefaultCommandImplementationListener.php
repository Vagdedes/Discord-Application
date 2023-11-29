<?php

use Discord\Builders\MessageBuilder;
use Discord\Parts\Embed\Embed;
use Discord\Parts\Interactions\Interaction;

class DefaultCommandImplementationListener
{

    public static function close_ticket(DiscordPlan $plan,
                                        Interaction $interaction,
                                        object      $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $argumentSize = sizeof($arguments);

        if ($argumentSize === 0) {
            $close = $plan->userTickets->closeByChannel($interaction->channel, $interaction->user->id);

            if ($close !== null) {
                $plan->utilities->acknowledgeCommandMessage(
                    $interaction,
                    MessageBuilder::new()->setContent("Ticket could not be closed: " . $close),
                    true
                );
            }
        } else {
            $hasReason = $argumentSize > 1;
            $ticketID = $arguments["ticket-id"]["value"] ?? null;

            if (is_numeric($ticketID)) {
                if ($hasReason) {
                    $close = $plan->userTickets->closeByID(
                        $ticketID,
                        $interaction->user->id,
                        $arguments["reason"]["value"] ?? null
                    );
                } else {
                    $close = $plan->userTickets->closeByID($ticketID, $interaction->user->id);
                }

                if ($close !== null) {
                    $plan->utilities->acknowledgeCommandMessage(
                        $interaction,
                        MessageBuilder::new()->setContent("Ticket could not be closed: " . $close),
                        true
                    );
                } else {
                    $plan->utilities->acknowledgeCommandMessage(
                        $interaction,
                        MessageBuilder::new()->setContent("Ticket successfully closed"),
                        true
                    );
                }
            } else {
                $close = $plan->userTickets->closeByChannel(
                    $interaction->channel,
                    $interaction->user->id,
                    $arguments["reason"]["value"] ?? null
                );

                if ($close !== null) {
                    $plan->utilities->acknowledgeCommandMessage(
                        $interaction,
                        MessageBuilder::new()->setContent("Ticket could not be closed: " . $close),
                        true
                    );
                }
            }
        }
    }

    public static function get_tickets(DiscordPlan $plan,
                                       Interaction $interaction,
                                       object      $command): void
    {
        $findUserID = $interaction->data?->resolved?->users?->first()?->id;
        $tickets = $plan->userTickets->getMultiple(
            $findUserID,
            null,
            DiscordInheritedLimits::MAX_EMBEDS_PER_MESSAGE,
            false
        );

        if (empty($tickets)) {
            $plan->utilities->acknowledgeCommandMessage(
                $interaction,
                MessageBuilder::new()->setContent("No tickets found for user."),
                true
            );
        } else {
            $plan->utilities->acknowledgeCommandMessage(
                $interaction,
                $plan->userTickets->loadTicketsMessage($findUserID, $tickets),
                true
            );
        }
    }

    public static function get_ticket(DiscordPlan $plan,
                                      Interaction $interaction,
                                      object      $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $ticketID = $arguments["ticket-id"]["value"] ?? null;

        if (!is_numeric($ticketID)) {
            $plan->utilities->acknowledgeCommandMessage(
                $interaction,
                MessageBuilder::new()->setContent("Invalid ticket-id argument."),
                true
            );
        }
        $ticket = $plan->userTickets->getSingle($ticketID);

        if ($ticket === null) {
            $plan->utilities->acknowledgeCommandMessage(
                $interaction,
                MessageBuilder::new()->setContent("Ticket not found."),
                true
            );
        } else {
            $plan->utilities->acknowledgeCommandMessage(
                $interaction,
                $plan->userTickets->loadSingleTicketMessage($ticket),
                true
            );
        }
    }

    // Separator

    public static function list_commands(DiscordPlan $plan,
                                         Interaction $interaction,
                                         object      $command): void
    {
        $content = "";

        foreach (array(
                     $plan->commands->staticCommands,
                     $plan->commands->dynamicCommands,
                     $plan->commands->nativeCommands
                 ) as $commands) {
            if (!empty($commands)) {
                foreach ($commands as $command) {
                    if ($command->required_permission !== null
                        && $plan->permissions->hasPermission(
                            $interaction->member,
                            $command->required_permission
                        )) {
                        $content .= "__" . $command->id . "__ "
                            . $command->command_placeholder
                            . $command->command_identification . "\n";
                    }
                }
            }
        }
        $plan->utilities->acknowledgeCommandMessage(
            $interaction,
            MessageBuilder::new()->setContent(
                empty($content) ? "No commands found." : $content
            ),
            true
        );
    }

    // Separator

    public static function close_target(DiscordPlan $plan,
                                        Interaction $interaction,
                                        object      $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $argumentSize = sizeof($arguments);

        if ($argumentSize === 0) {
            $close = $plan->userTargets->closeByChannelOrThread(
                $interaction->channel,
                $interaction->user->id
            );

            if ($close !== null) {
                $plan->utilities->acknowledgeCommandMessage(
                    $interaction,
                    MessageBuilder::new()->setContent("Target could not be closed: " . $close),
                    true
                );
            }
        } else {
            $hasReason = $argumentSize > 1;
            $targetID = $arguments["target-id"]["value"] ?? null;

            if (is_numeric($targetID)) {
                if ($hasReason) {
                    $close = $plan->userTargets->closeByID(
                        $targetID,
                        $interaction->user->id,
                        $arguments["reason"]["value"] ?? null
                    );
                } else {
                    $close = $plan->userTargets->closeByID($targetID, $interaction->user->id);
                }

                if ($close !== null) {
                    $plan->utilities->acknowledgeCommandMessage(
                        $interaction,
                        MessageBuilder::new()->setContent("Target could not be closed: " . $close),
                        true
                    );
                } else {
                    $plan->utilities->acknowledgeCommandMessage(
                        $interaction,
                        MessageBuilder::new()->setContent("Target successfully closed"),
                        true
                    );
                }
            } else {
                $close = $plan->userTargets->closeByChannelOrThread(
                    $interaction->channel,
                    $interaction->user->id,
                    $arguments["reason"]["value"] ?? null
                );

                if ($close !== null) {
                    $plan->utilities->acknowledgeCommandMessage(
                        $interaction,
                        MessageBuilder::new()->setContent("Target could not be closed: " . $close),
                        true
                    );
                }
            }
        }
    }

    public static function get_targets(DiscordPlan $plan,
                                       Interaction $interaction,
                                       object      $command): void
    {
        $findUserID = $interaction->data?->resolved?->users?->first()?->id;
        $targets = $plan->userTargets->getMultiple(
            $findUserID,
            null,
            DiscordInheritedLimits::MAX_EMBEDS_PER_MESSAGE,
            false
        );

        if (empty($targets)) {
            $plan->utilities->acknowledgeCommandMessage(
                $interaction,
                MessageBuilder::new()->setContent("No targets found for user."),
                true
            );
        } else {
            $plan->utilities->acknowledgeCommandMessage(
                $interaction,
                $plan->userTargets->loadTargetsMessage($findUserID, $targets),
                true
            );
        }
    }

    public static function get_target(DiscordPlan $plan,
                                      Interaction $interaction,
                                      object      $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $targetID = $arguments["target-id"]["value"] ?? null;

        if (!is_numeric($targetID)) {
            $plan->utilities->acknowledgeCommandMessage(
                $interaction,
                MessageBuilder::new()->setContent("Invalid target-id argument."),
                true
            );
        }
        $target = $plan->userTargets->getSingle($targetID);

        if ($target === null) {
            $plan->utilities->acknowledgeCommandMessage(
                $interaction,
                MessageBuilder::new()->setContent("Target not found."),
                true
            );
        } else {
            $plan->utilities->acknowledgeCommandMessage(
                $interaction,
                $plan->userTargets->loadSingleTargetMessage($target),
                true
            );
        }
    }

    // Separator

    public static function list_counting_goals(DiscordPlan $plan,
                                               Interaction $interaction,
                                               object      $command): void
    {
        $findUserID = $interaction->data?->resolved?->users?->first()?->id;
        $goals = $plan->countingChannels->getStoredGoals(
            $findUserID,
            DiscordInheritedLimits::MAX_EMBEDS_PER_MESSAGE
        );

        if (empty($goals)) {
            $plan->utilities->acknowledgeCommandMessage(
                $interaction,
                MessageBuilder::new()->setContent("No goals found for user."),
                true
            );
        } else {
            $plan->utilities->acknowledgeCommandMessage(
                $interaction,
                $plan->countingChannels->loadStoredGoalMessages($findUserID, $goals),
                true
            );
        }
    }

    public static function create_note(DiscordPlan $plan,
                                       Interaction $interaction,
                                       object      $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $plan->userNotes->create(
            $interaction,
            $arguments["key"]["value"],
            $arguments["reason"]["value"] ?? null
        );
    }

    public static function edit_note(DiscordPlan $plan,
                                     Interaction $interaction,
                                     object      $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $plan->userNotes->edit(
            $interaction,
            $arguments["key"]["value"],
            $interaction->data?->resolved?->users?->first()?->id,
            $arguments["reason"]["value"] ?? null
        );
    }

    public static function get_note(DiscordPlan $plan,
                                    Interaction $interaction,
                                    object      $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $plan->userNotes->send(
            $interaction,
            $arguments["key"]["value"],
            $interaction->data?->resolved?->users?->first()?->id
        );
    }

    public static function get_notes(DiscordPlan $plan,
                                     Interaction $interaction,
                                     object      $command): void
    {
        $plan->userNotes->sendAll(
            $interaction,
            $interaction->data?->resolved?->users?->first()?->id
        );
    }

    public static function delete_note(DiscordPlan $plan,
                                       Interaction $interaction,
                                       object      $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $plan->userNotes->delete(
            $interaction,
            $arguments["key"]["value"],
            $interaction->data?->resolved?->users?->first()?->id,
            $arguments["reason"]["value"] ?? null
        );
    }

    public static function modify_note_setting(DiscordPlan $plan,
                                               Interaction $interaction,
                                               object      $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $plan->userNotes->changeSetting(
            $interaction,
            $arguments["key"]["value"],
            $interaction->data?->resolved?->users?->first()?->id,
            $arguments["view-public"]["value"] ?? false,
            $arguments["read-history"]["value"] ?? false
        );
    }

    public static function modify_note_participant(DiscordPlan $plan,
                                                   Interaction $interaction,
                                                   object      $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $plan->userNotes->modifyParticipant(
            $interaction,
            $arguments["key"]["value"],
            $interaction->data?->resolved?->users?->first()?->id,
            $interaction->data?->resolved?->users?->last()?->id,
            $arguments["read-history"]["value"] ?? false,
            $arguments["write-permission"]["value"] ?? false,
            $arguments["delete-permission"]["value"] ?? false,
            $arguments["manage-permission"]["value"] ?? false
        );
    }

    public static function invite_stats(DiscordPlan $plan,
                                        Interaction $interaction,
                                        object      $command): void
    {
        $user = $interaction->data?->resolved?->users?->first();

        if ($user !== null) {
            $object = $plan->inviteTracker->getUserStats(
                $interaction->guild_id,
                $user->id
            );
            $messageBuilder = MessageBuilder::new();
            $embed = new Embed($plan->discord);
            $embed->setAuthor($user->username, $user->avatar);
            $embed->addFieldValues("Total Invite Links", $object->total_invite_links);
            $embed->addFieldValues("Active Invite Links", $object->active_invite_links);
            $embed->addFieldValues("Users Invited", $object->users_invited);
            $messageBuilder->addEmbed($embed);

            $goals = $plan->inviteTracker->getStoredGoals(
                $interaction->guild_id,
                $user->id,
                DiscordInheritedLimits::MAX_EMBEDS_PER_MESSAGE - 1
            );

            if (!empty($goals)) {
                foreach ($goals as $goal) {
                    $embed = new Embed($plan->discord);
                    $embed->setTitle($goal->title);

                    if ($goal->description !== null) {
                        $embed->setDescription($goal->description);
                    }
                    $embed->setTimestamp(strtotime($goal->creation_date));
                    $messageBuilder->addEmbed($embed);
                }
            }

            $plan->utilities->acknowledgeCommandMessage(
                $interaction,
                $messageBuilder,
                true
            );
        } else {
            $array = $plan->inviteTracker->getServerStats(
                $interaction->guild_id,
            );
            $size = sizeof($array);

            if ($size > 0) {
                $messageBuilder = MessageBuilder::new();
                $counter = 0;

                foreach ($array as $object) {
                    $user = $plan->utilities->getUser($object->user_id);

                    if ($user !== null) {
                        $counter++;
                        $embed = new Embed($plan->discord);
                        $embed->setAuthor($counter . ". " . $user->username, $user->avatar);
                        $embed->addFieldValues("Total Invite Links", $object->total_invite_links);
                        $embed->addFieldValues("Active Invite Links", $object->active_invite_links);
                        $embed->addFieldValues("Users Invited", $object->users_invited);
                        $messageBuilder->addEmbed($embed);

                        if ($counter === DiscordInheritedLimits::MAX_EMBEDS_PER_MESSAGE) {
                            break;
                        }
                    }
                }

                if ($counter === 0) {
                    $plan->utilities->acknowledgeCommandMessage(
                        $interaction,
                        MessageBuilder::new()->setContent("No relevant invite stats found."),
                        true
                    );
                } else {
                    $plan->utilities->acknowledgeCommandMessage(
                        $interaction,
                        $messageBuilder,
                        true
                    );
                }
            } else {
                $plan->utilities->acknowledgeCommandMessage(
                    $interaction,
                    MessageBuilder::new()->setContent("No relevant invite stats found."),
                    true
                );
            }
        }
    }
}