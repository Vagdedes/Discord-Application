<?php

use Discord\Builders\MessageBuilder;
use Discord\Parts\Channel\Message;
use Discord\Parts\Embed\Embed;
use Discord\Parts\Interactions\Interaction;

class DefaultCommandImplementationListener
{

    private const AI_IMAGE_HASH = 978323941;

    public static function generate_image(DiscordBot          $bot,
                                          Interaction|Message $interaction,
                                          object              $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $prompt = $arguments["prompt"]["value"] ?? null;
        $xResolution = $arguments["x-resolution"]["value"] ?? null;
        $yResolution = $arguments["y-resolution"]["value"] ?? null;
        $hd = $arguments["hd"]["value"] ?? false;
        $private = $arguments["private"]["value"] ?? false;
        $pastMessages = $arguments["past-messages"]["value"] ?? 0;
        $initialPrompt = "Please wait...";
        $interaction->channel->getMessageHistory(
            [
                'limit' => max(min($pastMessages, 100), 1),
                'cache' => true
            ]
        )->done(function ($messageHistory)
        use ($interaction, $bot, $prompt, $xResolution, $yResolution, $hd, $pastMessages, $initialPrompt, $private) {
            $messageHistory = $messageHistory->toArray();

            $bot->utilities->acknowledgeCommandMessage(
                $interaction,
                MessageBuilder::new()->setContent($initialPrompt),
                $private
            )->done(function ()
            use ($interaction, $bot, $prompt, $xResolution, $yResolution, $hd, $pastMessages, $initialPrompt, $messageHistory) {
                $promptLimit = 4000;
                $arguments = array(
                    "n" => 1,
                    "prompt" => $prompt,
                    "size" => $xResolution . "x" . $yResolution,
                );
                $addedPrompts = false;

                if ($pastMessages > 0) {
                    while (strlen($arguments["prompt"]) < $promptLimit
                        && !empty($messageHistory)
                        && $pastMessages > 0) {
                        $newPrompt = array_shift($messageHistory)->content;

                        if (empty($newPrompt)
                            || $newPrompt === $initialPrompt) {
                            continue;
                        }
                        $newPrompt .= "\n\n";

                        if (strlen($arguments["prompt"]) + strlen($newPrompt) > $promptLimit) {
                            break;
                        }
                        $arguments["prompt"] = $newPrompt . $arguments["prompt"];
                        $pastMessages--;
                        $addedPrompts = true;
                    }
                }
                if ($hd) {
                    $arguments["quality"] = "hd";
                }
                $managerAI = new ManagerAI(
                    AIModelFamily::DALLE_3,
                    AIHelper::getAuthorization(AIAuthorization::OPENAI),
                    $arguments
                );
                $outcome = $managerAI->getResult(
                    self::AI_IMAGE_HASH
                );

                if (array_shift($outcome)) {
                    $image = $outcome[0]->getImage($outcome[1]);
                    $messageBuilder = MessageBuilder::new()->setContent($prompt);
                    $embed = new Embed($bot->discord);
                    $embed->setImage($image);

                    if ($addedPrompts) {
                        $embed->setDescription($arguments["prompt"]);
                    }
                    $messageBuilder->addEmbed($embed);
                    $interaction->updateOriginalResponse($messageBuilder);
                } else {
                    $interaction->updateOriginalResponse(
                        MessageBuilder::new()->setContent("Failed to generate image: " . json_encode($outcome[1]))
                    );
                }
            });
        });
    }

    public static function find_message_reference(DiscordBot          $bot,
                                                  Interaction|Message $interaction,
                                                  object              $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $query = get_sql_query(
            BotDatabaseTable::BOT_LOGS,
            array("object"),
            array(
                array("server_id", $interaction->guild_id),
                array("action", "MESSAGE_CREATE")
            ),
            array(
                "DESC",
                "id"
            ),
            10_000
        );

        if (!empty($query)) {
            $messageID = $arguments["message-id"]["value"] ?? null;

            if (is_numeric($messageID)) {
                foreach ($query as $row) {
                    $row = json_decode($row->object, false);

                    if ($row->id == $messageID) {
                        if (isset($row->message_reference->message_id)) {
                            foreach ($query as $rowChild) {
                                $rowChildObject = json_decode($rowChild->object, false);

                                if ($rowChildObject->id == $row->message_reference->message_id) {
                                    $bot->utilities->acknowledgeCommandMessage(
                                        $interaction,
                                        MessageBuilder::new()->setContent(substr(
                                            $rowChild->object, 0, DiscordInheritedLimits::MESSAGE_MAX_LENGTH
                                        )),
                                        true
                                    );
                                }
                            }
                        } else {
                            $bot->utilities->acknowledgeCommandMessage(
                                $interaction,
                                MessageBuilder::new()->setContent("Message found but not reference."),
                                true
                            );
                        }
                        return;
                    }
                }

                $bot->utilities->acknowledgeCommandMessage(
                    $interaction,
                    MessageBuilder::new()->setContent("Message not found."),
                    true
                );
            } else {
                $bot->utilities->acknowledgeCommandMessage(
                    $interaction,
                    MessageBuilder::new()->setContent("ID is not numeric."),
                    true
                );
            }
        } else {
            $bot->utilities->acknowledgeCommandMessage(
                $interaction,
                MessageBuilder::new()->setContent("No database rows returned."),
                true
            );
        }
    }

    public static function toggle_ai(DiscordBot          $bot,
                                     Interaction|Message $interaction,
                                     object              $command): void
    {
        // todo
        $bot->utilities->acknowledgeCommandMessage(
            $interaction,
            MessageBuilder::new()->setContent("Not implemented"),
            true
        );
    }

    public static function create_faq(DiscordBot          $bot,
                                      Interaction|Message $interaction,
                                      object              $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $message = $bot->faq->addOrEdit(
            $interaction,
            $arguments["question"]["value"] ?? null,
            $arguments["answer"]["value"] ?? null
        );

        if ($message === null) {
            $bot->utilities->acknowledgeCommandMessage(
                $interaction,
                MessageBuilder::new()->setContent("Frequently Asked Question successfully created or edited."),
                true
            );
        } else {
            $bot->utilities->acknowledgeCommandMessage(
                $interaction,
                MessageBuilder::new()->setContent($message),
                true
            );
        }
    }

    public static function delete_faq(DiscordBot          $bot,
                                      Interaction|Message $interaction,
                                      object              $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $message = $bot->faq->delete(
            $interaction,
            $arguments["question"]["value"] ?? null
        );

        if ($message === null) {
            $bot->utilities->acknowledgeCommandMessage(
                $interaction,
                MessageBuilder::new()->setContent("Frequently Asked Question successfully deleted if any."),
                true
            );
        } else {
            $bot->utilities->acknowledgeCommandMessage(
                $interaction,
                MessageBuilder::new()->setContent($message),
                true
            );
        }
    }

    public static function get_faq(DiscordBot          $bot,
                                   Interaction|Message $interaction,
                                   object              $command): void
    {
        $bot->utilities->acknowledgeCommandMessage(
            $interaction,
            $bot->faq->list($interaction),
            true
        );
    }

    // Separator

    public static function set_ai_cost_limit(DiscordBot          $bot,
                                             Interaction|Message $interaction,
                                             object              $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $timePeriod = $arguments["time-period"]["value"] ?? null;

        if (!is_valid_text_time($timePeriod)) {
            $bot->utilities->acknowledgeCommandMessage(
                $interaction,
                MessageBuilder::new()->setContent("Invalid time format."),
                true
            );
        } else {
            $message = $bot->aiMessages->setLimit(
                $interaction,
                true,
                $arguments["currency-limit"]["value"] ?? null,
                $timePeriod,
                $arguments["per-user"]["value"] ?? null,
                $arguments["time-out"]["value"] ?? null,
                $arguments["message"]["value"] ?? null,
                $interaction->data?->resolved?->roles?->first(),
                $interaction->data?->resolved?->channels?->first()
            );

            if ($message === null) {
                $bot->utilities->acknowledgeCommandMessage(
                    $interaction,
                    MessageBuilder::new()->setContent("Cost limit successfully set."),
                    true
                );
            } else {
                $bot->utilities->acknowledgeCommandMessage(
                    $interaction,
                    MessageBuilder::new()->setContent($message),
                    true
                );
            }
        }
    }

    public static function set_ai_message_limit(DiscordBot          $bot,
                                                Interaction|Message $interaction,
                                                object              $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $timePeriod = $arguments["time-period"]["value"] ?? null;

        if (!is_valid_text_time($timePeriod)) {
            $bot->utilities->acknowledgeCommandMessage(
                $interaction,
                MessageBuilder::new()->setContent("Invalid time format."),
                true
            );
        } else {
            $message = $bot->aiMessages->setLimit(
                $interaction,
                false,
                $arguments["message-limit"]["value"] ?? null,
                $timePeriod,
                $arguments["per-user"]["value"] ?? null,
                $arguments["time-out"]["value"] ?? null,
                $arguments["message"]["value"] ?? null,
                $interaction->data?->resolved?->roles?->first(),
                $interaction->data?->resolved?->channels?->first()
            );

            if ($message === null) {
                $bot->utilities->acknowledgeCommandMessage(
                    $interaction,
                    MessageBuilder::new()->setContent("Message limit successfully set."),
                    true
                );
            } else {
                $bot->utilities->acknowledgeCommandMessage(
                    $interaction,
                    MessageBuilder::new()->setContent($message),
                    true
                );
            }
        }
    }

    public static function remove_ai_cost_limit(DiscordBot          $bot,
                                                Interaction|Message $interaction,
                                                object              $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $timePeriod = $arguments["time-period"]["value"] ?? null;

        if (!is_valid_text_time($timePeriod)) {
            $bot->utilities->acknowledgeCommandMessage(
                $interaction,
                MessageBuilder::new()->setContent("Invalid time format."),
                true
            );
        } else {
            $message = $bot->aiMessages->setLimit(
                $interaction,
                true,
                null,
                $timePeriod,
                $arguments["per-user"]["value"] ?? null,
                null,
                null,
                $interaction->data?->resolved?->roles?->first(),
                $interaction->data?->resolved?->channels?->first(),
                false
            );

            if ($message === null) {
                $bot->utilities->acknowledgeCommandMessage(
                    $interaction,
                    MessageBuilder::new()->setContent("Successfully deleted any cost limit associated."),
                    true
                );
            } else {
                $bot->utilities->acknowledgeCommandMessage(
                    $interaction,
                    MessageBuilder::new()->setContent($message),
                    true
                );
            }
        }
    }

    public static function remove_ai_message_limit(DiscordBot          $bot,
                                                   Interaction|Message $interaction,
                                                   object              $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $timePeriod = $arguments["time-period"]["value"] ?? null;

        if (!is_valid_text_time($timePeriod)) {
            $bot->utilities->acknowledgeCommandMessage(
                $interaction,
                MessageBuilder::new()->setContent("Invalid time format."),
                true
            );
        } else {
            $message = $bot->aiMessages->setLimit(
                $interaction,
                false,
                null,
                $timePeriod,
                $arguments["per-user"]["value"] ?? null,
                null,
                null,
                $interaction->data?->resolved?->roles?->first(),
                $interaction->data?->resolved?->channels?->first(),
                false
            );

            if ($message === null) {
                $bot->utilities->acknowledgeCommandMessage(
                    $interaction,
                    MessageBuilder::new()->setContent("Successfully deleted any AI limit associated."),
                    true
                );
            } else {
                $bot->utilities->acknowledgeCommandMessage(
                    $interaction,
                    MessageBuilder::new()->setContent($message),
                    true
                );
            }
        }
    }

    // Separator

    public static function create_giveaway(DiscordBot          $bot,
                                           Interaction|Message $interaction,
                                           object              $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $bot->utilities->acknowledgeCommandMessage(
            $interaction,
            $bot->userGiveaways->create(
                $interaction,
                $arguments["name"]["value"] ?? null,
                $arguments["title"]["value"] ?? null,
                $arguments["description"]["value"] ?? null,
                $arguments["minimum-participants"]["value"] ?? null,
                $arguments["maximum-participants"]["value"] ?? null,
                $arguments["winner-amount"]["value"] ?? null,
                $arguments["repeat-after-ending"]["value"] ?? null
            ) ?? MessageBuilder::new()->setContent("Giveaway successfully created."),
            true
        );
    }

    public static function delete_giveaway(DiscordBot          $bot,
                                           Interaction|Message $interaction,
                                           object              $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $bot->utilities->acknowledgeCommandMessage(
            $interaction,
            $bot->userGiveaways->delete(
                $interaction,
                $arguments["name"]["value"] ?? null
            ) ?? MessageBuilder::new()->setContent("Giveaway successfully deleted."),
            true
        );
    }

    public static function start_giveaway(DiscordBot          $bot,
                                          Interaction|Message $interaction,
                                          object              $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $bot->utilities->acknowledgeCommandMessage(
            $interaction,
            $bot->userGiveaways->start(
                $interaction,
                $arguments["name"]["value"] ?? null,
                $arguments["duration"]["value"] ?? null,
            ) ?? MessageBuilder::new()->setContent("Giveaway successfully started."),
            true
        );
    }

    public static function end_giveaway(DiscordBot          $bot,
                                        Interaction|Message $interaction,
                                        object              $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $bot->utilities->acknowledgeCommandMessage(
            $interaction,
            $bot->userGiveaways->end(
                $interaction,
                $arguments["name"]["value"] ?? null
            ) ?? MessageBuilder::new()->setContent("Giveaway successfully ended."),
            true
        );
    }

    public static function add_giveaway_permission(DiscordBot          $bot,
                                                   Interaction|Message $interaction,
                                                   object              $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $bot->utilities->acknowledgeCommandMessage(
            $interaction,
            $bot->userGiveaways->setRequiredPermission(
                $interaction,
                $arguments["name"]["value"] ?? null,
                $arguments["permission"]["value"] ?? null
            ) ?? MessageBuilder::new()->setContent("Giveaway required permission successfully added."),
            true
        );
    }

    public static function remove_giveaway_permission(DiscordBot          $bot,
                                                      Interaction|Message $interaction,
                                                      object              $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $bot->utilities->acknowledgeCommandMessage(
            $interaction,
            $bot->userGiveaways->setRequiredPermission(
                $interaction,
                $arguments["name"]["value"] ?? null,
                $arguments["permission"]["value"] ?? null,
                false
            ) ?? MessageBuilder::new()->setContent("Giveaway required permission successfully removed."),
            true
        );
    }

    public static function add_giveaway_role(DiscordBot          $bot,
                                             Interaction|Message $interaction,
                                             object              $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $bot->utilities->acknowledgeCommandMessage(
            $interaction,
            $bot->userGiveaways->setRequiredRole(
                $interaction,
                $arguments["name"]["value"] ?? null,
                $interaction->data?->resolved?->roles?->first()->id
            ) ?? MessageBuilder::new()->setContent("Giveaway required role successfully added."),
            true
        );
    }

    public static function remove_giveaway_role(DiscordBot          $bot,
                                                Interaction|Message $interaction,
                                                object              $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $bot->utilities->acknowledgeCommandMessage(
            $interaction,
            $bot->userGiveaways->setRequiredRole(
                $interaction,
                $arguments["name"]["value"] ?? null,
                $interaction->data?->resolved?->roles?->first()->id,
                false
            ) ?? MessageBuilder::new()->setContent("Giveaway required role successfully removed."),
            true
        );
    }

    // Separator

    public static function create_embed_message(DiscordBot          $bot,
                                                Interaction|Message $interaction,
                                                object              $command): void
    {
        $arguments = $interaction->data->options->toArray();

        $message = MessageBuilder::new();
        $embed = new Embed($bot->discord);
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

        $bot->utilities->acknowledgeCommandMessage(
            $interaction,
            $message,
            $arguments["ephemeral"]["value"] ?? null
        );
    }

    public static function mute_user(DiscordBot          $bot,
                                     Interaction|Message $interaction,
                                     object              $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $type = $arguments["type"]["value"] ?? null;

        switch ($type) {
            case DiscordMute::VOICE:
            case DiscordMute::TEXT:
            case DiscordMute::COMMAND:
                break;
            case "all":
                $type = DiscordMute::ALL;
                break;
            default:
                $bot->utilities->acknowledgeCommandMessage(
                    $interaction,
                    MessageBuilder::new()->setContent("Invalid mute type."),
                    true
                );
                return;
        }
        $duration = $arguments["duration"]["value"] ?? null;

        if ($duration !== null && !is_valid_text_time($duration)) {
            $bot->utilities->acknowledgeCommandMessage(
                $interaction,
                MessageBuilder::new()->setContent("Invalid mute duration."),
                true
            );
        } else {
            $mute = $bot->mute->mute(
                $interaction->member,
                $interaction->data?->resolved?->members?->first(),
                $interaction->data?->resolved?->channels?->first(),
                $arguments["reason"]["value"] ?? null,
                strtolower($type),
                $duration !== null ? strtolower($duration) : null
            );

            if ($mute[0]) {
                $important = $mute[1];
                $bot->utilities->acknowledgeCommandMessage(
                    $interaction,
                    MessageBuilder::new()->setContent("User is already by '"
                        . $bot->utilities->getUsername($important->created_by)
                        . "' muted for: " . $important->creation_reason),
                    true
                );
            } else {
                $important = $mute[1];

                if (is_string($important)) {
                    $bot->utilities->acknowledgeCommandMessage(
                        $interaction,
                        MessageBuilder::new()->setContent($important),
                        true
                    );
                } else {
                    $bot->utilities->acknowledgeCommandMessage(
                        $interaction,
                        MessageBuilder::new()->setContent("User successfully muted."),
                        true
                    );
                }
            }
        }
    }

    public static function unmute_user(DiscordBot          $bot,
                                       Interaction|Message $interaction,
                                       object              $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $type = $arguments["type"]["value"] ?? null;

        switch ($type) {
            case DiscordMute::VOICE:
            case DiscordMute::TEXT:
            case DiscordMute::COMMAND:
                break;
            case "all":
                $type = DiscordMute::ALL;
                break;
            default:
                $bot->utilities->acknowledgeCommandMessage(
                    $interaction,
                    MessageBuilder::new()->setContent("Invalid mute type."),
                    true
                );
                return;
        }
        $unmute = $bot->mute->unmute(
            $interaction->member,
            $interaction->data?->resolved?->members?->first(),
            $interaction->data?->resolved?->channels?->first(),
            $arguments["reason"]["value"] ?? null,
            strtolower($type)
        );

        if (empty($unmute)) {
            $bot->utilities->acknowledgeCommandMessage(
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
                $bot->utilities->acknowledgeCommandMessage(
                    $interaction,
                    MessageBuilder::new()->setContent(
                        "User " . ($negative > 0 ? "partly" : "successfully") . " unmuted."
                    ),
                    true
                );
            } else {
                $bot->utilities->acknowledgeCommandMessage(
                    $interaction,
                    MessageBuilder::new()->setContent("User failed to be unmuted."),
                    true
                );

            }
        }
    }

    // Separator

    public static function temporary_channel_lock(DiscordBot          $bot,
                                                  Interaction|Message $interaction,
                                                  object              $command): void
    {
        $outcome = $bot->temporaryChannels->setLock($interaction->member);

        if ($outcome === null) {
            $outcome = "Temporary channel successfully locked.";
        }
        $bot->utilities->acknowledgeCommandMessage(
            $interaction,
            MessageBuilder::new()->setContent($outcome),
            true
        );
    }

    public static function temporary_channel_unlock(DiscordBot          $bot,
                                                    Interaction|Message $interaction,
                                                    object              $command): void
    {
        $outcome = $bot->temporaryChannels->setLock(
            $interaction->member,
            false
        );

        if ($outcome === null) {
            $outcome = "Temporary channel successfully unlocked.";
        }
        $bot->utilities->acknowledgeCommandMessage(
            $interaction,
            MessageBuilder::new()->setContent($outcome),
            true
        );
    }

    public static function temporary_channel_ban(DiscordBot          $bot,
                                                 Interaction|Message $interaction,
                                                 object              $command): void
    {
        $outcome = $bot->temporaryChannels->setBan(
            $interaction->member,
            $interaction->data?->resolved?->members?->first(),
            true,
            $arguments["reason"]["value"] ?? null
        );

        if ($outcome === null) {
            $outcome = "User successfully banned from this temporary channel.";
        }
        $bot->utilities->acknowledgeCommandMessage(
            $interaction,
            MessageBuilder::new()->setContent($outcome),
            true
        );
    }

    public static function temporary_channel_unban(DiscordBot          $bot,
                                                   Interaction|Message $interaction,
                                                   object              $command): void
    {
        $outcome = $bot->temporaryChannels->setBan(
            $interaction->member,
            $interaction->data?->resolved?->members?->first(),
            false,
            $arguments["reason"]["value"] ?? null
        );

        if ($outcome === null) {
            $outcome = "User successfully unbanned in this temporary channel.";
        }
        $bot->utilities->acknowledgeCommandMessage(
            $interaction,
            MessageBuilder::new()->setContent($outcome),
            true
        );
    }

    public static function temporary_channel_add_owner(DiscordBot          $bot,
                                                       Interaction|Message $interaction,
                                                       object              $command): void
    {
        $outcome = $bot->temporaryChannels->setOwner(
            $interaction->member,
            $interaction->data?->resolved?->members?->first(),
            true,
            $arguments["reason"]["value"] ?? null
        );

        if ($outcome === null) {
            $outcome = "User successfully made an owner in this temporary channel.";
        }
        $bot->utilities->acknowledgeCommandMessage(
            $interaction,
            MessageBuilder::new()->setContent($outcome),
            true
        );
    }

    public static function temporary_channel_remove_owner(DiscordBot          $bot,
                                                          Interaction|Message $interaction,
                                                          object              $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $outcome = $bot->temporaryChannels->setOwner(
            $interaction->member,
            $interaction->data?->resolved?->members?->first(),
            false,
            $arguments["reason"]["value"] ?? null
        );

        if ($outcome === null) {
            $outcome = "User successfully removed from owner in this temporary channel.";
        }
        $bot->utilities->acknowledgeCommandMessage(
            $interaction,
            MessageBuilder::new()->setContent($outcome),
            true
        );
    }

    // Separator

    public static function close_ticket(DiscordBot          $bot,
                                        Interaction|Message $interaction,
                                        object              $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $argumentSize = sizeof($arguments);

        if ($argumentSize === 0) {
            $close = $bot->userTickets->closeByChannel($interaction->channel, $interaction->user->id);

            if ($close !== null) {
                $bot->utilities->acknowledgeCommandMessage(
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
                    $close = $bot->userTickets->closeByID(
                        $ticketID,
                        $interaction->user->id,
                        $arguments["reason"]["value"] ?? null
                    );
                } else {
                    $close = $bot->userTickets->closeByID($ticketID, $interaction->user->id);
                }

                if ($close !== null) {
                    $bot->utilities->acknowledgeCommandMessage(
                        $interaction,
                        MessageBuilder::new()->setContent("Ticket could not be closed: " . $close),
                        true
                    );
                } else {
                    $bot->utilities->acknowledgeCommandMessage(
                        $interaction,
                        MessageBuilder::new()->setContent("Ticket successfully closed"),
                        true
                    );
                }
            } else {
                $close = $bot->userTickets->closeByChannel(
                    $interaction->channel,
                    $interaction->user->id,
                    $arguments["reason"]["value"] ?? null
                );

                if ($close !== null) {
                    $bot->utilities->acknowledgeCommandMessage(
                        $interaction,
                        MessageBuilder::new()->setContent("Ticket could not be closed: " . $close),
                        true
                    );
                }
            }
        }
    }

    public static function get_tickets(DiscordBot          $bot,
                                       Interaction|Message $interaction,
                                       object              $command): void
    {
        $findUserID = $interaction->data?->resolved?->users?->first()?->id;
        $tickets = $bot->userTickets->getMultiple(
            $interaction->guild_id,
            $findUserID,
            null,
            DiscordInheritedLimits::MAX_EMBEDS_PER_MESSAGE,
            false
        );

        if (empty($tickets)) {
            $bot->utilities->acknowledgeCommandMessage(
                $interaction,
                MessageBuilder::new()->setContent("No tickets found for user."),
                true
            );
        } else {
            $bot->utilities->acknowledgeCommandMessage(
                $interaction,
                $bot->userTickets->loadTicketsMessage($findUserID, $tickets),
                true
            );
        }
    }

    public static function get_ticket(DiscordBot          $bot,
                                      Interaction|Message $interaction,
                                      object              $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $ticketID = $arguments["ticket-id"]["value"] ?? null;

        if (!is_numeric($ticketID)) {
            $bot->utilities->acknowledgeCommandMessage(
                $interaction,
                MessageBuilder::new()->setContent("Invalid ticket-id argument."),
                true
            );
        }
        $ticket = $bot->userTickets->getSingle($ticketID);

        if ($ticket === null) {
            $bot->utilities->acknowledgeCommandMessage(
                $interaction,
                MessageBuilder::new()->setContent("Ticket not found."),
                true
            );
        } else {
            $bot->utilities->acknowledgeCommandMessage(
                $interaction,
                $bot->userTickets->loadSingleTicketMessage($ticket),
                true
            );
        }
    }

    // Separator

    public static function list_commands(DiscordBot          $bot,
                                         Interaction|Message $interaction,
                                         object              $command): void
    {
        $content = "";

        foreach (array(
                     $bot->commands->staticCommands,
                     $bot->commands->dynamicCommands,
                     $bot->commands->nativeCommands
                 ) as $commands) {
            if (!empty($commands)) {
                foreach ($commands as $command) {
                    if ($command->required_permission !== null
                        && $bot->permissions->hasPermission(
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
        $bot->utilities->acknowledgeCommandMessage(
            $interaction,
            MessageBuilder::new()->setContent(
                empty($content) ? "No commands found." : $content
            ),
            true
        );
    }

    // Separator

    public static function close_questionnaire(DiscordBot          $bot,
                                               Interaction|Message $interaction,
                                               object              $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $argumentSize = sizeof($arguments);

        if ($argumentSize === 0) {
            $close = $bot->userQuestionnaire->closeByChannelOrThread(
                $interaction->channel,
                $interaction->user->id
            );

            if ($close !== null) {
                $bot->utilities->acknowledgeCommandMessage(
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
                    $close = $bot->userQuestionnaire->closeByID(
                        $targetID,
                        $interaction->user->id,
                        $arguments["reason"]["value"] ?? null
                    );
                } else {
                    $close = $bot->userQuestionnaire->closeByID($targetID, $interaction->user->id);
                }

                if ($close !== null) {
                    $bot->utilities->acknowledgeCommandMessage(
                        $interaction,
                        MessageBuilder::new()->setContent("Questionnaire could not be closed: " . $close),
                        true
                    );
                } else {
                    $bot->utilities->acknowledgeCommandMessage(
                        $interaction,
                        MessageBuilder::new()->setContent("Questionnaire successfully closed"),
                        true
                    );
                }
            } else {
                $close = $bot->userQuestionnaire->closeByChannelOrThread(
                    $interaction->channel,
                    $interaction->user->id,
                    $arguments["reason"]["value"] ?? null
                );

                if ($close !== null) {
                    $bot->utilities->acknowledgeCommandMessage(
                        $interaction,
                        MessageBuilder::new()->setContent("Questionnaire could not be closed: " . $close),
                        true
                    );
                }
            }
        }
    }

    public static function get_questionnaires(DiscordBot          $bot,
                                              Interaction|Message $interaction,
                                              object              $command): void
    {
        $findUserID = $interaction->data?->resolved?->users?->first()?->id;
        $questionnaires = $bot->userQuestionnaire->getMultiple(
            $interaction->guild_id,
            $findUserID,
            null,
            DiscordInheritedLimits::MAX_EMBEDS_PER_MESSAGE,
            false,
            -1
        );

        if (empty($questionnaires)) {
            $bot->utilities->acknowledgeCommandMessage(
                $interaction,
                MessageBuilder::new()->setContent("No questionnaires found for user."),
                true
            );
        } else {
            $bot->utilities->acknowledgeCommandMessage(
                $interaction,
                $bot->userQuestionnaire->loadQuestionnaireMessage($findUserID, $questionnaires),
                true
            );
        }
    }

    public static function get_questionnaire(DiscordBot          $bot,
                                             Interaction|Message $interaction,
                                             object              $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $questionnaireID = $arguments["target-id"]["value"] ?? null;

        if (!is_numeric($questionnaireID)) {
            $bot->utilities->acknowledgeCommandMessage(
                $interaction,
                MessageBuilder::new()->setContent("Invalid questionnaire-id argument."),
                true
            );
        }
        $target = $bot->userQuestionnaire->getSingle($questionnaireID, -1);

        if ($target === null) {
            $bot->utilities->acknowledgeCommandMessage(
                $interaction,
                MessageBuilder::new()->setContent("Questionnaire not found."),
                true
            );
        } else {
            $bot->utilities->acknowledgeCommandMessage(
                $interaction,
                $bot->userQuestionnaire->loadSingleQuestionnaireMessage($target),
                true
            );
        }
    }

    // Separator

    public static function close_target(DiscordBot          $bot,
                                        Interaction|Message $interaction,
                                        object              $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $argumentSize = sizeof($arguments);

        if ($argumentSize === 0) {
            $close = $bot->userTargets->closeByChannelOrThread(
                $interaction->channel,
                $interaction->user->id
            );

            if ($close !== null) {
                $bot->utilities->acknowledgeCommandMessage(
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
                    $close = $bot->userTargets->closeByID(
                        $targetID,
                        $interaction->user->id,
                        $arguments["reason"]["value"] ?? null
                    );
                } else {
                    $close = $bot->userTargets->closeByID($targetID, $interaction->user->id);
                }

                if ($close !== null) {
                    $bot->utilities->acknowledgeCommandMessage(
                        $interaction,
                        MessageBuilder::new()->setContent("Target could not be closed: " . $close),
                        true
                    );
                } else {
                    $bot->utilities->acknowledgeCommandMessage(
                        $interaction,
                        MessageBuilder::new()->setContent("Target successfully closed"),
                        true
                    );
                }
            } else {
                $close = $bot->userTargets->closeByChannelOrThread(
                    $interaction->channel,
                    $interaction->user->id,
                    $arguments["reason"]["value"] ?? null
                );

                if ($close !== null) {
                    $bot->utilities->acknowledgeCommandMessage(
                        $interaction,
                        MessageBuilder::new()->setContent("Target could not be closed: " . $close),
                        true
                    );
                }
            }
        }
    }

    public static function get_targets(DiscordBot          $bot,
                                       Interaction|Message $interaction,
                                       object              $command): void
    {
        $findUserID = $interaction->data?->resolved?->users?->first()?->id;
        $targets = $bot->userTargets->getMultiple(
            $interaction->guild_id,
            $findUserID,
            null,
            DiscordInheritedLimits::MAX_EMBEDS_PER_MESSAGE,
            false
        );

        if (empty($targets)) {
            $bot->utilities->acknowledgeCommandMessage(
                $interaction,
                MessageBuilder::new()->setContent("No targets found for user."),
                true
            );
        } else {
            $bot->utilities->acknowledgeCommandMessage(
                $interaction,
                $bot->userTargets->loadTargetsMessage($findUserID, $targets),
                true
            );
        }
    }

    public static function get_target(DiscordBot          $bot,
                                      Interaction|Message $interaction,
                                      object              $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $targetID = $arguments["target-id"]["value"] ?? null;

        if (!is_numeric($targetID)) {
            $bot->utilities->acknowledgeCommandMessage(
                $interaction,
                MessageBuilder::new()->setContent("Invalid target-id argument."),
                true
            );
        }
        $target = $bot->userTargets->getSingle($targetID);

        if ($target === null) {
            $bot->utilities->acknowledgeCommandMessage(
                $interaction,
                MessageBuilder::new()->setContent("Target not found."),
                true
            );
        } else {
            $bot->utilities->acknowledgeCommandMessage(
                $interaction,
                $bot->userTargets->loadSingleTargetMessage($target),
                true
            );
        }
    }

    // Separator

    public static function list_counting_goals(DiscordBot          $bot,
                                               Interaction|Message $interaction,
                                               object              $command): void
    {
        $findUserID = $interaction->data?->resolved?->users?->first()?->id;
        $goals = $bot->countingChannels->getStoredGoals(
            $findUserID,
            DiscordInheritedLimits::MAX_EMBEDS_PER_MESSAGE
        );

        if (empty($goals)) {
            $bot->utilities->acknowledgeCommandMessage(
                $interaction,
                MessageBuilder::new()->setContent("No goals found for user."),
                true
            );
        } else {
            $bot->utilities->acknowledgeCommandMessage(
                $interaction,
                $bot->countingChannels->loadStoredGoalMessages($findUserID, $goals),
                true
            );
        }
    }

    public static function create_note(DiscordBot          $bot,
                                       Interaction|Message $interaction,
                                       object              $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $bot->userNotes->create(
            $interaction,
            $arguments["key"]["value"] ?? null,
            $arguments["reason"]["value"] ?? null
        );
    }

    public static function edit_note(DiscordBot          $bot,
                                     Interaction|Message $interaction,
                                     object              $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $bot->userNotes->edit(
            $interaction,
            $arguments["key"]["value"] ?? null,
            $interaction->data?->resolved?->users?->first()?->id,
            $arguments["reason"]["value"] ?? null
        );
    }

    public static function get_note(DiscordBot          $bot,
                                    Interaction|Message $interaction,
                                    object              $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $bot->userNotes->send(
            $interaction,
            $arguments["key"]["value"] ?? null,
            $interaction->data?->resolved?->users?->first()?->id
        );
    }

    public static function get_notes(DiscordBot          $bot,
                                     Interaction|Message $interaction,
                                     object              $command): void
    {
        $bot->userNotes->sendAll(
            $interaction,
            $interaction->data?->resolved?->users?->first()?->id
        );
    }

    public static function delete_note(DiscordBot          $bot,
                                       Interaction|Message $interaction,
                                       object              $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $bot->userNotes->delete(
            $interaction,
            $arguments["key"]["value"] ?? null,
            $interaction->data?->resolved?->users?->first()?->id,
            $arguments["reason"]["value"] ?? null
        );
    }

    public static function modify_note_setting(DiscordBot          $bot,
                                               Interaction|Message $interaction,
                                               object              $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $bot->userNotes->changeSetting(
            $interaction,
            $arguments["key"]["value"] ?? null,
            $interaction->data?->resolved?->users?->first()?->id,
            $arguments["view-public"]["value"] ?? false,
            $arguments["read-history"]["value"] ?? false
        );
    }

    public static function modify_note_participant(DiscordBot          $bot,
                                                   Interaction|Message $interaction,
                                                   object              $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $bot->userNotes->modifyParticipant(
            $interaction,
            $arguments["key"]["value"] ?? null,
            $interaction->data?->resolved?->users?->first()?->id,
            $interaction->data?->resolved?->users?->last()?->id,
            $arguments["read-history"]["value"] ?? false,
            $arguments["write-permission"]["value"] ?? false,
            $arguments["delete-permission"]["value"] ?? false,
            $arguments["manage-permission"]["value"] ?? false
        );
    }

    public static function invite_stats(DiscordBot          $bot,
                                        Interaction|Message $interaction,
                                        object              $command): void
    {
        $user = $interaction->data?->resolved?->users?->first();

        if ($user !== null) {
            $object = $bot->inviteTracker->getUserStats(
                $interaction->guild_id,
                $user->id
            );
            $messageBuilder = MessageBuilder::new();
            $embed = new Embed($bot->discord);
            $embed->setAuthor($user->username, $user->avatar);
            $embed->addFieldValues("Total Invite Links", $object->total_invite_links);
            $embed->addFieldValues("Active Invite Links", $object->active_invite_links);
            $embed->addFieldValues("Users Invited", $object->users_invited);
            $messageBuilder->addEmbed($embed);

            $goals = $bot->inviteTracker->getStoredGoals(
                $interaction->guild_id,
                $user->id,
                DiscordInheritedLimits::MAX_EMBEDS_PER_MESSAGE - 1
            );

            if (!empty($goals)) {
                foreach ($goals as $goal) {
                    $embed = new Embed($bot->discord);
                    $embed->setTitle($goal->title);

                    if ($goal->description !== null) {
                        $embed->setDescription($goal->description);
                    }
                    $embed->setTimestamp(strtotime($goal->creation_date));
                    $messageBuilder->addEmbed($embed);
                }
            }

            $bot->utilities->acknowledgeCommandMessage(
                $interaction,
                $messageBuilder,
                true
            );
        } else {
            $array = $bot->inviteTracker->getServerStats(
                $interaction->guild_id,
            );
            $size = sizeof($array);

            if ($size > 0) {
                $messageBuilder = MessageBuilder::new();
                $counter = 0;

                foreach ($array as $object) {
                    $user = $bot->utilities->getUser($object->user_id);

                    if ($user !== null) {
                        $counter++;
                        $embed = new Embed($bot->discord);
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
                    $bot->utilities->acknowledgeCommandMessage(
                        $interaction,
                        MessageBuilder::new()->setContent("No relevant invite stats found."),
                        true
                    );
                } else {
                    $bot->utilities->acknowledgeCommandMessage(
                        $interaction,
                        $messageBuilder,
                        true
                    );
                }
            } else {
                $bot->utilities->acknowledgeCommandMessage(
                    $interaction,
                    MessageBuilder::new()->setContent("No relevant invite stats found."),
                    true
                );
            }
        }
    }

    public static function get_user_level(DiscordBot          $bot,
                                          Interaction|Message $interaction,
                                          object              $command): void
    {
        $user = $interaction->data?->resolved?->users?->first();
        $object = $bot->userLevels->getTier(
            $interaction->guild_id,
            $interaction->channel_id,
            $user?->id,
            null,
            true
        );

        if (is_string($object)) {
            $bot->utilities->acknowledgeCommandMessage(
                $interaction,
                MessageBuilder::new()->setContent($object),
                true
            );
        } else {
            $messageBuilder = MessageBuilder::new();
            $embed = new Embed($bot->discord);
            $embed->setAuthor($user->username, $user->avatar);
            $embed->setTitle($object[0]->tier_name);
            $embed->setDescription($object[0]->tier_description);
            $embed->setFooter($object[1] . " Points");
            $messageBuilder->addEmbed($embed);
            $bot->utilities->acknowledgeCommandMessage(
                $interaction,
                $messageBuilder,
                true
            );
        }
    }

    public static function set_user_level(DiscordBot          $bot,
                                          Interaction|Message $interaction,
                                          object              $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $process = $bot->userLevels->setLevel(
            $interaction->guild_id,
            $interaction->channel_id,
            $interaction->data?->resolved?->users?->first()?->id,
            $arguments["amount"]["value"] ?? null
        );

        if (is_string($process)) {
            $bot->utilities->acknowledgeCommandMessage(
                $interaction,
                MessageBuilder::new()->setContent($process),
                true
            );
        } else {
            $bot->utilities->acknowledgeCommandMessage(
                $interaction,
                MessageBuilder::new()->setContent("User level successfully increased."),
                true
            );
        }
    }

    public static function increase_user_level(DiscordBot          $bot,
                                               Interaction|Message $interaction,
                                               object              $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $process = $bot->userLevels->increaseLevel(
            $interaction->guild_id,
            $interaction->channel_id,
            $interaction->data?->resolved?->users?->first()?->id,
            $arguments["amount"]["value"] ?? null
        );

        if (is_string($process)) {
            $bot->utilities->acknowledgeCommandMessage(
                $interaction,
                MessageBuilder::new()->setContent($process),
                true
            );
        } else {
            $bot->utilities->acknowledgeCommandMessage(
                $interaction,
                MessageBuilder::new()->setContent("User level successfully increased."),
                true
            );
        }
    }

    public static function decrease_user_level(DiscordBot          $bot,
                                               Interaction|Message $interaction,
                                               object              $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $process = $bot->userLevels->decreaseLevel(
            $interaction->guild_id,
            $interaction->channel_id,
            $interaction->data?->resolved?->users?->first()?->id,
            $arguments["amount"]["value"] ?? null
        );

        if (is_string($process)) {
            $bot->utilities->acknowledgeCommandMessage(
                $interaction,
                MessageBuilder::new()->setContent($process),
                true
            );
        } else {
            $bot->utilities->acknowledgeCommandMessage(
                $interaction,
                MessageBuilder::new()->setContent("User level successfully decreased."),
                true
            );
        }
    }

    public static function reset_user_level(DiscordBot          $bot,
                                            Interaction|Message $interaction,
                                            object              $command): void
    {
        $process = $bot->userLevels->resetLevel(
            $interaction->guild_id,
            $interaction->channel_id,
            $interaction->data?->resolved?->users?->first()?->id
        );

        if (is_string($process)) {
            $bot->utilities->acknowledgeCommandMessage(
                $interaction,
                MessageBuilder::new()->setContent($process),
                true
            );
        } else {
            $bot->utilities->acknowledgeCommandMessage(
                $interaction,
                MessageBuilder::new()->setContent("User level successfully reset."),
                true
            );
        }
    }

    public static function get_level_leaderboard(DiscordBot          $bot,
                                                 Interaction|Message $interaction,
                                                 object              $command): void
    {
        $object = $bot->userLevels->getLevels(
            $interaction->guild_id,
            $interaction->channel_id,
        );

        if (is_string($object)) {
            $bot->utilities->acknowledgeCommandMessage(
                $interaction,
                MessageBuilder::new()->setContent($object),
                true
            );
        } else {
            $messageBuilder = MessageBuilder::new();
            $counter = 0;

            foreach ($object as $user) {
                $counter++;
                $embed = new Embed($bot->discord);
                $userObject = $bot->utilities->getUser($user->user_id);

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

            $bot->utilities->acknowledgeCommandMessage(
                $interaction,
                $messageBuilder,
                true
            );
        }
    }
}