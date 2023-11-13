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
                $plan->utilities->acknowledgeMessage(
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
                    $plan->utilities->acknowledgeMessage(
                        $interaction,
                        MessageBuilder::new()->setContent("Ticket could not be closed: " . $close),
                        true
                    );
                } else {
                    $plan->utilities->acknowledgeMessage(
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
                    $plan->utilities->acknowledgeMessage(
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
            $plan->utilities->acknowledgeMessage(
                $interaction,
                MessageBuilder::new()->setContent("Missing user argument."),
                true
            );
        } else if ($argumentSize > 1) {
            $plan->utilities->acknowledgeMessage(
                $interaction,
                MessageBuilder::new()->setContent("Too many arguments."),
                true
            );
        } else {
            $findUserID = $interaction->data->resolved->users->first()->id;
            $tickets = $plan->ticket->getMultiple(
                $findUserID,
                null,
                DiscordProperties::MAX_EMBED_PER_MESSAGE,
                false
            );

            if (empty($tickets)) {
                $plan->utilities->acknowledgeMessage(
                    $interaction,
                    MessageBuilder::new()->setContent("No tickets found for user."),
                    true
                );
            } else {
                $plan->utilities->acknowledgeMessage(
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
            $plan->utilities->acknowledgeMessage(
                $interaction,
                MessageBuilder::new()->setContent("Missing ticket-id argument."),
                true
            );
        } else if ($argumentSize > 1) {
            $plan->utilities->acknowledgeMessage(
                $interaction,
                MessageBuilder::new()->setContent("Too many arguments."),
                true
            );
        } else {
            $ticketID = array_shift($arguments)["value"];

            if (!is_numeric($ticketID)) {
                $plan->utilities->acknowledgeMessage(
                    $interaction,
                    MessageBuilder::new()->setContent("Invalid ticket-id argument."),
                    true
                );
            }
            $ticket = $plan->ticket->getSingle($ticketID);

            if ($ticket === null) {
                $plan->utilities->acknowledgeMessage(
                    $interaction,
                    MessageBuilder::new()->setContent("Ticket not found."),
                    true
                );
            } else {
                $plan->utilities->acknowledgeMessage(
                    $interaction,
                    $plan->ticket->loadSingleTicketMessage($ticket),
                    true
                );
            }
        }
    }
}