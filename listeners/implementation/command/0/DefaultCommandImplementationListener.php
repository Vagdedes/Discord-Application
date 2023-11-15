<?php

use Discord\Builders\MessageBuilder;
use Discord\Parts\Interactions\Interaction;

class DefaultCommandImplementationListener // Name can be changed
{

    public static function close_ticket(DiscordPlan $plan,
                                        Interaction $interaction,
                                        object      $command): void // Name can be changed
    {
        $arguments = $interaction->data->options->toArray();
        $argumentSize = sizeof($arguments);

        if ($argumentSize === 0) {
            $close = $plan->ticket->closeByChannel($interaction->channel, $interaction->user->id);

            if ($close !== null) {
                $plan->utilities->acknowledgeCommandMessage(
                    $interaction,
                    MessageBuilder::new()->setContent("Ticket could not be closed: " . $close),
                    true
                );
            }
        } else {
            $hasReason = $argumentSize > 1;
            $ticketID = array_shift($arguments)["value"];

            if (is_numeric($ticketID)) {
                if ($hasReason) {
                    $close = $plan->ticket->closeByID(
                        $ticketID,
                        $interaction->user->id,
                        implode(" ", $arguments)
                    );
                } else {
                    $close = $plan->ticket->closeByID($ticketID, $interaction->user->id);
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
                $close = $plan->ticket->closeByChannel(
                    $interaction->channel,
                    $interaction->user->id,
                    implode(" ", $arguments)
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
                                       object      $command): void // Name can be changed
    {
        $arguments = $interaction->data->options->toArray();
        $argumentSize = sizeof($arguments);

        if ($argumentSize === 0) {
            $plan->utilities->acknowledgeCommandMessage(
                $interaction,
                MessageBuilder::new()->setContent("Missing user argument."),
                true
            );
        } else if ($argumentSize > 1) {
            $plan->utilities->acknowledgeCommandMessage(
                $interaction,
                MessageBuilder::new()->setContent("Too many arguments."),
                true
            );
        } else {
            $findUserID = $interaction->data->resolved->users->first()->id;
            $tickets = $plan->ticket->getMultiple(
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
                    $plan->ticket->loadTicketsMessage($findUserID, $tickets),
                    true
                );
            }
        }
    }

    public static function get_ticket(DiscordPlan $plan,
                                      Interaction $interaction,
                                      object      $command): void // Name can be changed
    {
        $arguments = $interaction->data->options->toArray();
        $argumentSize = sizeof($arguments);

        if ($argumentSize === 0) {
            $plan->utilities->acknowledgeCommandMessage(
                $interaction,
                MessageBuilder::new()->setContent("Missing ticket-id argument."),
                true
            );
        } else if ($argumentSize > 1) {
            $plan->utilities->acknowledgeCommandMessage(
                $interaction,
                MessageBuilder::new()->setContent("Too many arguments."),
                true
            );
        } else {
            $ticketID = array_shift($arguments)["value"];

            if (!is_numeric($ticketID)) {
                $plan->utilities->acknowledgeCommandMessage(
                    $interaction,
                    MessageBuilder::new()->setContent("Invalid ticket-id argument."),
                    true
                );
            }
            $ticket = $plan->ticket->getSingle($ticketID);

            if ($ticket === null) {
                $plan->utilities->acknowledgeCommandMessage(
                    $interaction,
                    MessageBuilder::new()->setContent("Ticket not found."),
                    true
                );
            } else {
                $plan->utilities->acknowledgeCommandMessage(
                    $interaction,
                    $plan->ticket->loadSingleTicketMessage($ticket),
                    true
                );
            }
        }
    }

    // Separator

    public static function list_commands(DiscordPlan $plan,
                                         Interaction $interaction,
                                         object      $command): void // Name can be changed
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
                                        object      $command): void // Name can be changed
    {
        $arguments = $interaction->data->options->toArray();
        $argumentSize = sizeof($arguments);

        if ($argumentSize === 0) {
            $close = $plan->target->closeByChannel($interaction->channel, $interaction->user->id);

            if ($close !== null) {
                $plan->utilities->acknowledgeCommandMessage(
                    $interaction,
                    MessageBuilder::new()->setContent("Target could not be closed: " . $close),
                    true
                );
            }
        } else {
            $hasReason = $argumentSize > 1;
            $targetID = array_shift($arguments)["value"];

            if (is_numeric($targetID)) {
                if ($hasReason) {
                    $close = $plan->target->closeByID(
                        $targetID,
                        $interaction->user->id,
                        implode(" ", $arguments)
                    );
                } else {
                    $close = $plan->target->closeByID($targetID, $interaction->user->id);
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
                $close = $plan->target->closeByChannel(
                    $interaction->channel,
                    $interaction->user->id,
                    implode(" ", $arguments)
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
                                       object      $command): void // Name can be changed
    {
        $arguments = $interaction->data->options->toArray();
        $argumentSize = sizeof($arguments);

        if ($argumentSize === 0) {
            $plan->utilities->acknowledgeCommandMessage(
                $interaction,
                MessageBuilder::new()->setContent("Missing user argument."),
                true
            );
        } else if ($argumentSize > 1) {
            $plan->utilities->acknowledgeCommandMessage(
                $interaction,
                MessageBuilder::new()->setContent("Too many arguments."),
                true
            );
        } else {
            $findUserID = $interaction->data->resolved->users->first()->id;
            $targets = $plan->target->getMultiple(
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
                    $plan->target->loadTargetsMessage($findUserID, $targets),
                    true
                );
            }
        }
    }

    public static function get_target(DiscordPlan $plan,
                                      Interaction $interaction,
                                      object      $command): void // Name can be changed
    {
        $arguments = $interaction->data->options->toArray();
        $argumentSize = sizeof($arguments);

        if ($argumentSize === 0) {
            $plan->utilities->acknowledgeCommandMessage(
                $interaction,
                MessageBuilder::new()->setContent("Missing target-id argument."),
                true
            );
        } else if ($argumentSize > 1) {
            $plan->utilities->acknowledgeCommandMessage(
                $interaction,
                MessageBuilder::new()->setContent("Too many arguments."),
                true
            );
        } else {
            $targetID = array_shift($arguments)["value"];

            if (!is_numeric($targetID)) {
                $plan->utilities->acknowledgeCommandMessage(
                    $interaction,
                    MessageBuilder::new()->setContent("Invalid target-id argument."),
                    true
                );
            }
            $target = $plan->target->getSingle($targetID);

            if ($target === null) {
                $plan->utilities->acknowledgeCommandMessage(
                    $interaction,
                    MessageBuilder::new()->setContent("Target not found."),
                    true
                );
            } else {
                $plan->utilities->acknowledgeCommandMessage(
                    $interaction,
                    $plan->target->loadSingleTargetMessage($target),
                    true
                );
            }
        }
    }

}