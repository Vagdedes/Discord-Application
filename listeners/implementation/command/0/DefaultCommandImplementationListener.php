<?php

use Discord\Builders\MessageBuilder;
use Discord\Parts\Embed\Embed;
use Discord\Parts\Interactions\Interaction;

class DefaultCommandImplementationListener
{

    public static function create_faq(DiscordPlan $plan,
                                      Interaction $interaction,
                                      object      $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $message = $plan->faq->addOrEdit(
            $interaction,
            $arguments["question"]["value"],
            $arguments["answer"]["value"]
        );

        if ($message === null) {
            $plan->utilities->acknowledgeCommandMessage(
                $interaction,
                MessageBuilder::new()->setContent("Frequently Asked Question successfully created or edited."),
                true
            );
        } else {
            $plan->utilities->acknowledgeCommandMessage(
                $interaction,
                MessageBuilder::new()->setContent($message),
                true
            );
        }
    }

    public static function delete_faq(DiscordPlan $plan,
                                      Interaction $interaction,
                                      object      $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $message = $plan->faq->delete(
            $interaction,
            $arguments["question"]["value"]
        );

        if ($message === null) {
            $plan->utilities->acknowledgeCommandMessage(
                $interaction,
                MessageBuilder::new()->setContent("Frequently Asked Question successfully deleted if any."),
                true
            );
        } else {
            $plan->utilities->acknowledgeCommandMessage(
                $interaction,
                MessageBuilder::new()->setContent($message),
                true
            );
        }
    }

    public static function get_faq(DiscordPlan $plan,
                                   Interaction $interaction,
                                   object      $command): void
    {
        $plan->utilities->acknowledgeCommandMessage(
            $interaction,
            $plan->faq->list($interaction),
            true
        );
    }

    // Separator

    public static function set_ai_cost_limit(DiscordPlan $plan,
                                             Interaction $interaction,
                                             object      $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $timePeriod = $arguments["time-period"]["value"];

        if (!is_valid_text_time($timePeriod)) {
            $plan->utilities->acknowledgeCommandMessage(
                $interaction,
                MessageBuilder::new()->setContent("Invalid time format."),
                true
            );
        } else {
            $message = $plan->aiMessages->setLimit(
                $interaction,
                true,
                $arguments["currency-limit"]["value"],
                $timePeriod,
                $arguments["per-user"]["value"],
                $arguments["time-out"]["value"],
                $arguments["message"]["value"],
                $interaction->data?->resolved?->roles?->first(),
                $interaction->data?->resolved?->channels?->first()
            );

            if ($message === null) {
                $plan->utilities->acknowledgeCommandMessage(
                    $interaction,
                    MessageBuilder::new()->setContent("Cost limit successfully set."),
                    true
                );
            } else {
                $plan->utilities->acknowledgeCommandMessage(
                    $interaction,
                    MessageBuilder::new()->setContent($message),
                    true
                );
            }
        }
    }

    public static function set_ai_message_limit(DiscordPlan $plan,
                                                Interaction $interaction,
                                                object      $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $timePeriod = $arguments["time-period"]["value"];

        if (!is_valid_text_time($timePeriod)) {
            $plan->utilities->acknowledgeCommandMessage(
                $interaction,
                MessageBuilder::new()->setContent("Invalid time format."),
                true
            );
        } else {
            $message = $plan->aiMessages->setLimit(
                $interaction,
                false,
                $arguments["message-limit"]["value"],
                $timePeriod,
                $arguments["per-user"]["value"],
                $arguments["time-out"]["value"],
                $arguments["message"]["value"],
                $interaction->data?->resolved?->roles?->first(),
                $interaction->data?->resolved?->channels?->first()
            );

            if ($message === null) {
                $plan->utilities->acknowledgeCommandMessage(
                    $interaction,
                    MessageBuilder::new()->setContent("Message limit successfully set."),
                    true
                );
            } else {
                $plan->utilities->acknowledgeCommandMessage(
                    $interaction,
                    MessageBuilder::new()->setContent($message),
                    true
                );
            }
        }
    }

    public static function remove_ai_cost_limit(DiscordPlan $plan,
                                                Interaction $interaction,
                                                object      $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $timePeriod = $arguments["time-period"]["value"];

        if (!is_valid_text_time($timePeriod)) {
            $plan->utilities->acknowledgeCommandMessage(
                $interaction,
                MessageBuilder::new()->setContent("Invalid time format."),
                true
            );
        } else {
            $message = $plan->aiMessages->setLimit(
                $interaction,
                true,
                null,
                $timePeriod,
                $arguments["per-user"]["value"],
                null,
                null,
                $interaction->data?->resolved?->roles?->first(),
                $interaction->data?->resolved?->channels?->first(),
                false
            );

            if ($message === null) {
                $plan->utilities->acknowledgeCommandMessage(
                    $interaction,
                    MessageBuilder::new()->setContent("Successfully deleted any cost limit associated."),
                    true
                );
            } else {
                $plan->utilities->acknowledgeCommandMessage(
                    $interaction,
                    MessageBuilder::new()->setContent($message),
                    true
                );
            }
        }
    }

    public static function remove_ai_message_limit(DiscordPlan $plan,
                                                   Interaction $interaction,
                                                   object      $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $timePeriod = $arguments["time-period"]["value"];

        if (!is_valid_text_time($timePeriod)) {
            $plan->utilities->acknowledgeCommandMessage(
                $interaction,
                MessageBuilder::new()->setContent("Invalid time format."),
                true
            );
        } else {
            $message = $plan->aiMessages->setLimit(
                $interaction,
                false,
                null,
                $timePeriod,
                $arguments["per-user"]["value"],
                null,
                null,
                $interaction->data?->resolved?->roles?->first(),
                $interaction->data?->resolved?->channels?->first(),
                false
            );

            if ($message === null) {
                $plan->utilities->acknowledgeCommandMessage(
                    $interaction,
                    MessageBuilder::new()->setContent("Successfully deleted any AI limit associated."),
                    true
                );
            } else {
                $plan->utilities->acknowledgeCommandMessage(
                    $interaction,
                    MessageBuilder::new()->setContent($message),
                    true
                );
            }
        }
    }

    // Separator

    public static function create_giveaway(DiscordPlan $plan,
                                           Interaction $interaction,
                                           object      $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $plan->utilities->acknowledgeCommandMessage(
            $interaction,
            $plan->userGiveaways->create(
                $interaction,
                $arguments["name"]["value"],
                $arguments["title"]["value"],
                $arguments["description"]["value"],
                $arguments["minimum-participants"]["value"],
                $arguments["maximum-participants"]["value"],
                $arguments["winner-amount"]["value"],
                $arguments["repeat-after-ending"]["value"]
            ) ?? MessageBuilder::new()->setContent("Giveaway successfully created."),
            true
        );
    }

    public static function delete_giveaway(DiscordPlan $plan,
                                           Interaction $interaction,
                                           object      $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $plan->utilities->acknowledgeCommandMessage(
            $interaction,
            $plan->userGiveaways->delete(
                $interaction,
                $arguments["name"]["value"]
            ) ?? MessageBuilder::new()->setContent("Giveaway successfully deleted."),
            true
        );
    }

    public static function start_giveaway(DiscordPlan $plan,
                                          Interaction $interaction,
                                          object      $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $plan->utilities->acknowledgeCommandMessage(
            $interaction,
            $plan->userGiveaways->start(
                $interaction,
                $arguments["name"]["value"],
                $arguments["duration"]["value"],
            ) ?? MessageBuilder::new()->setContent("Giveaway successfully started."),
            true
        );
    }

    public static function end_giveaway(DiscordPlan $plan,
                                        Interaction $interaction,
                                        object      $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $plan->utilities->acknowledgeCommandMessage(
            $interaction,
            $plan->userGiveaways->end(
                $interaction,
                $arguments["name"]["value"]
            ) ?? MessageBuilder::new()->setContent("Giveaway successfully ended."),
            true
        );
    }

    public static function add_giveaway_permission(DiscordPlan $plan,
                                                   Interaction $interaction,
                                                   object      $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $plan->utilities->acknowledgeCommandMessage(
            $interaction,
            $plan->userGiveaways->setRequiredPermission(
                $interaction,
                $arguments["name"]["value"],
                $arguments["permission"]["value"]
            ) ?? MessageBuilder::new()->setContent("Giveaway required permission successfully added."),
            true
        );
    }

    public static function remove_giveaway_permission(DiscordPlan $plan,
                                                      Interaction $interaction,
                                                      object      $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $plan->utilities->acknowledgeCommandMessage(
            $interaction,
            $plan->userGiveaways->setRequiredPermission(
                $interaction,
                $arguments["name"]["value"],
                $arguments["permission"]["value"],
                false
            ) ?? MessageBuilder::new()->setContent("Giveaway required permission successfully removed."),
            true
        );
    }

    public static function add_giveaway_role(DiscordPlan $plan,
                                             Interaction $interaction,
                                             object      $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $plan->utilities->acknowledgeCommandMessage(
            $interaction,
            $plan->userGiveaways->setRequiredRole(
                $interaction,
                $arguments["name"]["value"],
                $interaction->data?->resolved?->roles?->first()->id
            ) ?? MessageBuilder::new()->setContent("Giveaway required role successfully added."),
            true
        );
    }

    public static function remove_giveaway_role(DiscordPlan $plan,
                                                Interaction $interaction,
                                                object      $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $plan->utilities->acknowledgeCommandMessage(
            $interaction,
            $plan->userGiveaways->setRequiredRole(
                $interaction,
                $arguments["name"]["value"],
                $interaction->data?->resolved?->roles?->first()->id,
                false
            ) ?? MessageBuilder::new()->setContent("Giveaway required role successfully removed."),
            true
        );
    }

    // Separator

    public static function create_poll(DiscordPlan $plan,
                                       Interaction $interaction,
                                       object      $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $plan->utilities->acknowledgeCommandMessage(
            $interaction,
            $plan->userPolls->create(
                $interaction,
                $arguments["name"]["value"],
                $arguments["title"]["value"],
                $arguments["description"]["value"],
                $arguments["allow-choice-deletion"]["value"],
                $arguments["max-choices"]["value"],
                $arguments["allow-same-choice"]["value"]
            ) ?? MessageBuilder::new()->setContent("Poll successfully created."),
            true
        );
    }

    public static function delete_poll(DiscordPlan $plan,
                                       Interaction $interaction,
                                       object      $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $plan->utilities->acknowledgeCommandMessage(
            $interaction,
            $plan->userPolls->delete(
                $interaction,
                $arguments["name"]["value"]
            ) ?? MessageBuilder::new()->setContent("Poll successfully deleted."),
            true
        );
    }

    public static function start_poll(DiscordPlan $plan,
                                      Interaction $interaction,
                                      object      $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $plan->utilities->acknowledgeCommandMessage(
            $interaction,
            $plan->userPolls->start(
                $interaction,
                $arguments["name"]["value"],
                $arguments["duration"]["value"],
            ) ?? MessageBuilder::new()->setContent("Poll successfully started."),
            true
        );
    }

    public static function end_poll(DiscordPlan $plan,
                                    Interaction $interaction,
                                    object      $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $plan->utilities->acknowledgeCommandMessage(
            $interaction,
            $plan->userPolls->end(
                $interaction,
                $arguments["name"]["value"]
            ) ?? MessageBuilder::new()->setContent("Poll successfully ended."),
            true
        );
    }

    public static function add_poll_choice(DiscordPlan $plan,
                                           Interaction $interaction,
                                           object      $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $plan->utilities->acknowledgeCommandMessage(
            $interaction,
            $plan->userPolls->setChoice(
                $interaction,
                $arguments["name"]["value"],
                $arguments["choice"]["value"],
                $arguments["description"]["value"]
            ) ?? MessageBuilder::new()->setContent("Poll choice successfully added."),
            true
        );
    }

    public static function remove_poll_choice(DiscordPlan $plan,
                                              Interaction $interaction,
                                              object      $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $plan->utilities->acknowledgeCommandMessage(
            $interaction,
            $plan->userPolls->setChoice(
                $interaction,
                $arguments["name"]["value"],
                $arguments["choice"]["value"],
                null,
                false
            ) ?? MessageBuilder::new()->setContent("Poll choice successfully removed."),
            true
        );
    }

    public static function add_poll_permission(DiscordPlan $plan,
                                               Interaction $interaction,
                                               object      $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $plan->utilities->acknowledgeCommandMessage(
            $interaction,
            $plan->userPolls->setRequiredPermission(
                $interaction,
                $arguments["name"]["value"],
                $arguments["permission"]["value"]
            ) ?? MessageBuilder::new()->setContent("Poll required permission successfully added."),
            true
        );
    }

    public static function remove_poll_permission(DiscordPlan $plan,
                                                  Interaction $interaction,
                                                  object      $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $plan->utilities->acknowledgeCommandMessage(
            $interaction,
            $plan->userPolls->setRequiredPermission(
                $interaction,
                $arguments["name"]["value"],
                $arguments["permission"]["value"],
                false
            ) ?? MessageBuilder::new()->setContent("Poll required permission successfully removed."),
            true
        );
    }

    public static function add_poll_role(DiscordPlan $plan,
                                         Interaction $interaction,
                                         object      $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $plan->utilities->acknowledgeCommandMessage(
            $interaction,
            $plan->userPolls->setRequiredRole(
                $interaction,
                $arguments["name"]["value"],
                $interaction->data?->resolved?->roles?->first()->id
            ) ?? MessageBuilder::new()->setContent("Poll required role successfully added."),
            true
        );
    }

    public static function remove_poll_role(DiscordPlan $plan,
                                            Interaction $interaction,
                                            object      $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $plan->utilities->acknowledgeCommandMessage(
            $interaction,
            $plan->userPolls->setRequiredRole(
                $interaction,
                $arguments["name"]["value"],
                $interaction->data?->resolved?->roles?->first()->id,
                false
            ) ?? MessageBuilder::new()->setContent("Poll required role successfully removed."),
            true
        );
    }

    // Separator

    public static function create_embed_message(DiscordPlan $plan,
                                                Interaction $interaction,
                                                object      $command): void
    {
        $arguments = $interaction->data->options->toArray();

        $message = MessageBuilder::new();
        $embed = new Embed($plan->bot->discord);
        $embed->setAuthor(
            $arguments["author-name"]["value"] ?? null,
            $arguments["author-icon-url"]["value"] ?? null,
            $arguments["author-redirect-url"]["value"] ?? null
        );
        $color = $arguments["color"]["value"] ?? null;

        if ($color !== null) {
            $embed->setColor($color);
        }
        $title = $arguments["title"]["value"] ?? null;

        if ($title !== null) {
            $embed->setTitle($title);
        }
        $description = $arguments["description"]["value"] ?? null;

        if ($description !== null) {
            $embed->setDescription($description);
        }
        $image = $arguments["image-url"]["value"] ?? null;

        if ($image !== null) {
            $embed->setImage($image);
        }
        $embed->setFooter(
            $arguments["footer-name"]["value"] ?? null,
            $arguments["footer-icon-url"]["value"] ?? null
        );
        $fields = $arguments["fields"]["value"] ?? null;

        if (!empty($fields)) {
            $fields = explode("//", $fields);

            foreach ($fields as $field) {
                $field = explode("/", $field);
                $embed->addFieldValues($field[0], $field[1], strtolower($field[2]) == "true");
            }
        }
        $message->addEmbed($embed);

        $plan->utilities->acknowledgeCommandMessage(
            $interaction,
            $message,
            $arguments["ephemeral"]["value"]
        );
    }

    public static function mute_user(DiscordPlan $plan,
                                     Interaction $interaction,
                                     object      $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $type = $arguments["type"]["value"];

        switch ($type) {
            case DiscordMute::VOICE:
            case DiscordMute::TEXT:
            case DiscordMute::COMMAND:
                break;
            case "all":
                $type = DiscordMute::ALL;
                break;
            default:
                $plan->utilities->acknowledgeCommandMessage(
                    $interaction,
                    MessageBuilder::new()->setContent("Invalid mute type."),
                    true
                );
                return;
        }
        $duration = $arguments["duration"]["value"] ?? null;

        if ($duration !== null && !is_valid_text_time($duration)) {
            $plan->utilities->acknowledgeCommandMessage(
                $interaction,
                MessageBuilder::new()->setContent("Invalid mute duration."),
                true
            );
        } else {
            $mute = $plan->bot->mute->mute(
                $interaction->member,
                $interaction->data?->resolved?->members?->first(),
                $interaction->data?->resolved?->channels?->first(),
                $arguments["reason"]["value"],
                strtolower($type),
                $duration !== null ? strtolower($duration) : null
            );

            if ($mute[0]) {
                $important = $mute[1];
                $plan->utilities->acknowledgeCommandMessage(
                    $interaction,
                    MessageBuilder::new()->setContent("User is already by '"
                        . $plan->utilities->getUsername($important->created_by)
                        . "' muted for: " . $important->creation_reason),
                    true
                );
            } else {
                $important = $mute[1];

                if (is_string($important)) {
                    $plan->utilities->acknowledgeCommandMessage(
                        $interaction,
                        MessageBuilder::new()->setContent($important),
                        true
                    );
                } else {
                    $plan->utilities->acknowledgeCommandMessage(
                        $interaction,
                        MessageBuilder::new()->setContent("User successfully muted."),
                        true
                    );
                }
            }
        }
    }

    public static function unmute_user(DiscordPlan $plan,
                                       Interaction $interaction,
                                       object      $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $type = $arguments["type"]["value"];

        switch ($type) {
            case DiscordMute::VOICE:
            case DiscordMute::TEXT:
            case DiscordMute::COMMAND:
                break;
            case "all":
                $type = DiscordMute::ALL;
                break;
            default:
                $plan->utilities->acknowledgeCommandMessage(
                    $interaction,
                    MessageBuilder::new()->setContent("Invalid mute type."),
                    true
                );
                return;
        }
        $unmute = $plan->bot->mute->unmute(
            $interaction->member,
            $interaction->data?->resolved?->members?->first(),
            $interaction->data?->resolved?->channels?->first(),
            $arguments["reason"]["value"],
            strtolower($type)
        );

        if (empty($unmute)) {
            $plan->utilities->acknowledgeCommandMessage(
                $interaction,
                MessageBuilder::new()->setContent("User is not muted."),
                true
            );
        } else {
            $positive = 0;
            $negative = 0;

            foreach ($unmute as $important) {
                if ($important[0]) {
                    $positive++;
                } else {
                    $negative++;
                }
            }

            if ($positive > 0) {
                $plan->utilities->acknowledgeCommandMessage(
                    $interaction,
                    MessageBuilder::new()->setContent(
                        "User " . ($negative > 0 ? "partly" : "successfully") . " unmuted."
                    ),
                    true
                );
            } else {
                $plan->utilities->acknowledgeCommandMessage(
                    $interaction,
                    MessageBuilder::new()->setContent("User failed to be unmuted."),
                    true
                );

            }
        }
    }

    // Separator

    public static function temporary_channel_lock(DiscordPlan $plan,
                                                  Interaction $interaction,
                                                  object      $command): void
    {
        $outcome = $plan->temporaryChannels->setLock($interaction->member);

        if ($outcome === null) {
            $outcome = "Temporary channel successfully locked.";
        }
        $plan->utilities->acknowledgeCommandMessage(
            $interaction,
            MessageBuilder::new()->setContent($outcome),
            true
        );
    }

    public static function temporary_channel_unlock(DiscordPlan $plan,
                                                    Interaction $interaction,
                                                    object      $command): void
    {
        $outcome = $plan->temporaryChannels->setLock(
            $interaction->member,
            false
        );

        if ($outcome === null) {
            $outcome = "Temporary channel successfully unlocked.";
        }
        $plan->utilities->acknowledgeCommandMessage(
            $interaction,
            MessageBuilder::new()->setContent($outcome),
            true
        );
    }

    public static function temporary_channel_ban(DiscordPlan $plan,
                                                 Interaction $interaction,
                                                 object      $command): void
    {
        $outcome = $plan->temporaryChannels->setBan(
            $interaction->member,
            $interaction->data?->resolved?->members?->first(),
            true,
            $arguments["reason"]["value"] ?? null
        );

        if ($outcome === null) {
            $outcome = "User successfully banned from this temporary channel.";
        }
        $plan->utilities->acknowledgeCommandMessage(
            $interaction,
            MessageBuilder::new()->setContent($outcome),
            true
        );
    }

    public static function temporary_channel_unban(DiscordPlan $plan,
                                                   Interaction $interaction,
                                                   object      $command): void
    {
        $outcome = $plan->temporaryChannels->setBan(
            $interaction->member,
            $interaction->data?->resolved?->members?->first(),
            false,
            $arguments["reason"]["value"] ?? null
        );

        if ($outcome === null) {
            $outcome = "User successfully unbanned in this temporary channel.";
        }
        $plan->utilities->acknowledgeCommandMessage(
            $interaction,
            MessageBuilder::new()->setContent($outcome),
            true
        );
    }

    public static function temporary_channel_add_owner(DiscordPlan $plan,
                                                       Interaction $interaction,
                                                       object      $command): void
    {
        $outcome = $plan->temporaryChannels->setOwner(
            $interaction->member,
            $interaction->data?->resolved?->members?->first(),
            true,
            $arguments["reason"]["value"] ?? null
        );

        if ($outcome === null) {
            $outcome = "User successfully made an owner in this temporary channel.";
        }
        $plan->utilities->acknowledgeCommandMessage(
            $interaction,
            MessageBuilder::new()->setContent($outcome),
            true
        );
    }

    public static function temporary_channel_remove_owner(DiscordPlan $plan,
                                                          Interaction $interaction,
                                                          object      $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $outcome = $plan->temporaryChannels->setOwner(
            $interaction->member,
            $interaction->data?->resolved?->members?->first(),
            false,
            $arguments["reason"]["value"] ?? null
        );

        if ($outcome === null) {
            $outcome = "User successfully removed from owner in this temporary channel.";
        }
        $plan->utilities->acknowledgeCommandMessage(
            $interaction,
            MessageBuilder::new()->setContent($outcome),
            true
        );
    }

    // Separator

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
            $interaction->guild_id,
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

    public static function close_questionnaire(DiscordPlan $plan,
                                               Interaction $interaction,
                                               object      $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $argumentSize = sizeof($arguments);

        if ($argumentSize === 0) {
            $close = $plan->userQuestionnaire->closeByChannelOrThread(
                $interaction->channel,
                $interaction->user->id
            );

            if ($close !== null) {
                $plan->utilities->acknowledgeCommandMessage(
                    $interaction,
                    MessageBuilder::new()->setContent("Questionnaire could not be closed: " . $close),
                    true
                );
            }
        } else {
            $hasReason = $argumentSize > 1;
            $targetID = $arguments["target-id"]["value"] ?? null;

            if (is_numeric($targetID)) {
                if ($hasReason) {
                    $close = $plan->userQuestionnaire->closeByID(
                        $targetID,
                        $interaction->user->id,
                        $arguments["reason"]["value"] ?? null
                    );
                } else {
                    $close = $plan->userQuestionnaire->closeByID($targetID, $interaction->user->id);
                }

                if ($close !== null) {
                    $plan->utilities->acknowledgeCommandMessage(
                        $interaction,
                        MessageBuilder::new()->setContent("Questionnaire could not be closed: " . $close),
                        true
                    );
                } else {
                    $plan->utilities->acknowledgeCommandMessage(
                        $interaction,
                        MessageBuilder::new()->setContent("Questionnaire successfully closed"),
                        true
                    );
                }
            } else {
                $close = $plan->userQuestionnaire->closeByChannelOrThread(
                    $interaction->channel,
                    $interaction->user->id,
                    $arguments["reason"]["value"] ?? null
                );

                if ($close !== null) {
                    $plan->utilities->acknowledgeCommandMessage(
                        $interaction,
                        MessageBuilder::new()->setContent("Questionnaire could not be closed: " . $close),
                        true
                    );
                }
            }
        }
    }

    public static function get_questionnaires(DiscordPlan $plan,
                                              Interaction $interaction,
                                              object      $command): void
    {
        $findUserID = $interaction->data?->resolved?->users?->first()?->id;
        $questionnaires = $plan->userQuestionnaire->getMultiple(
            $interaction->guild_id,
            $findUserID,
            null,
            DiscordInheritedLimits::MAX_EMBEDS_PER_MESSAGE,
            false,
            -1
        );

        if (empty($questionnaires)) {
            $plan->utilities->acknowledgeCommandMessage(
                $interaction,
                MessageBuilder::new()->setContent("No questionnaires found for user."),
                true
            );
        } else {
            $plan->utilities->acknowledgeCommandMessage(
                $interaction,
                $plan->userQuestionnaire->loadQuestionnaireMessage($findUserID, $questionnaires),
                true
            );
        }
    }

    public static function get_questionnaire(DiscordPlan $plan,
                                             Interaction $interaction,
                                             object      $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $questionnaireID = $arguments["target-id"]["value"] ?? null;

        if (!is_numeric($questionnaireID)) {
            $plan->utilities->acknowledgeCommandMessage(
                $interaction,
                MessageBuilder::new()->setContent("Invalid questionnaire-id argument."),
                true
            );
        }
        $target = $plan->userQuestionnaire->getSingle($questionnaireID, -1);

        if ($target === null) {
            $plan->utilities->acknowledgeCommandMessage(
                $interaction,
                MessageBuilder::new()->setContent("Questionnaire not found."),
                true
            );
        } else {
            $plan->utilities->acknowledgeCommandMessage(
                $interaction,
                $plan->userQuestionnaire->loadSingleQuestionnaireMessage($target),
                true
            );
        }
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
            $interaction->guild_id,
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
            $embed = new Embed($plan->bot->discord);
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
                    $embed = new Embed($plan->bot->discord);
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
                        $embed = new Embed($plan->bot->discord);
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

    public static function get_user_level(DiscordPlan $plan,
                                          Interaction $interaction,
                                          object      $command): void
    {
        $user = $interaction->data?->resolved?->users?->first();
        $object = $plan->userLevels->getTier(
            $interaction->guild_id,
            $interaction->channel_id,
            $user?->id,
            null,
            true
        );

        if (is_string($object)) {
            $plan->utilities->acknowledgeCommandMessage(
                $interaction,
                MessageBuilder::new()->setContent($object),
                true
            );
        } else {
            $messageBuilder = MessageBuilder::new();
            $embed = new Embed($plan->bot->discord);
            $embed->setAuthor($user->username, $user->avatar);
            $embed->setTitle($object[0]->tier_name);
            $embed->setDescription($object[0]->tier_description);
            $embed->setFooter($object[1] . " Points");
            $messageBuilder->addEmbed($embed);
            $plan->utilities->acknowledgeCommandMessage(
                $interaction,
                $messageBuilder,
                true
            );
        }
    }

    public static function set_user_level(DiscordPlan $plan,
                                          Interaction $interaction,
                                          object      $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $process = $plan->userLevels->setLevel(
            $interaction->guild_id,
            $interaction->channel_id,
            $interaction->data?->resolved?->users?->first()?->id,
            $arguments["amount"]["value"]
        );

        if (is_string($process)) {
            $plan->utilities->acknowledgeCommandMessage(
                $interaction,
                MessageBuilder::new()->setContent($process),
                true
            );
        } else {
            $plan->utilities->acknowledgeCommandMessage(
                $interaction,
                MessageBuilder::new()->setContent("User level successfully increased."),
                true
            );
        }
    }

    public static function increase_user_level(DiscordPlan $plan,
                                               Interaction $interaction,
                                               object      $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $process = $plan->userLevels->increaseLevel(
            $interaction->guild_id,
            $interaction->channel_id,
            $interaction->data?->resolved?->users?->first()?->id,
            $arguments["amount"]["value"]
        );

        if (is_string($process)) {
            $plan->utilities->acknowledgeCommandMessage(
                $interaction,
                MessageBuilder::new()->setContent($process),
                true
            );
        } else {
            $plan->utilities->acknowledgeCommandMessage(
                $interaction,
                MessageBuilder::new()->setContent("User level successfully increased."),
                true
            );
        }
    }

    public static function decrease_user_level(DiscordPlan $plan,
                                               Interaction $interaction,
                                               object      $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $process = $plan->userLevels->decreaseLevel(
            $interaction->guild_id,
            $interaction->channel_id,
            $interaction->data?->resolved?->users?->first()?->id,
            $arguments["amount"]["value"]
        );

        if (is_string($process)) {
            $plan->utilities->acknowledgeCommandMessage(
                $interaction,
                MessageBuilder::new()->setContent($process),
                true
            );
        } else {
            $plan->utilities->acknowledgeCommandMessage(
                $interaction,
                MessageBuilder::new()->setContent("User level successfully decreased."),
                true
            );
        }
    }

    public static function reset_user_level(DiscordPlan $plan,
                                            Interaction $interaction,
                                            object      $command): void
    {
        $process = $plan->userLevels->resetLevel(
            $interaction->guild_id,
            $interaction->channel_id,
            $interaction->data?->resolved?->users?->first()?->id
        );

        if (is_string($process)) {
            $plan->utilities->acknowledgeCommandMessage(
                $interaction,
                MessageBuilder::new()->setContent($process),
                true
            );
        } else {
            $plan->utilities->acknowledgeCommandMessage(
                $interaction,
                MessageBuilder::new()->setContent("User level successfully reset."),
                true
            );
        }
    }

    public static function get_level_leaderboard(DiscordPlan $plan,
                                                 Interaction $interaction,
                                                 object      $command): void
    {
        $object = $plan->userLevels->getLevels(
            $interaction->guild_id,
            $interaction->channel_id,
        );

        if (is_string($object)) {
            $plan->utilities->acknowledgeCommandMessage(
                $interaction,
                MessageBuilder::new()->setContent($object),
                true
            );
        } else {
            $messageBuilder = MessageBuilder::new();
            $counter = 0;

            foreach ($object as $user) {
                $counter++;
                $embed = new Embed($plan->bot->discord);
                $userObject = $plan->utilities->getUser($user->user_id);

                if ($userObject !== null) {
                    $embed->setAuthor($userObject->username, $userObject->avatar);
                } else {
                    $embed->setAuthor($user->user_id);
                }
                $embed->setTitle($user->tier->tier_name);
                $embed->setDescription($user->tier->tier_description);
                $embed->setFooter($user->level_points . " Points");
                $messageBuilder->addEmbed($embed);

                if ($counter === DiscordInheritedLimits::MAX_EMBEDS_PER_MESSAGE) {
                    break;
                }
            }

            $plan->utilities->acknowledgeCommandMessage(
                $interaction,
                $messageBuilder,
                true
            );
        }
    }
}