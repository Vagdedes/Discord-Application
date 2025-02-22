<?php

use Discord\Builders\MessageBuilder;
use Discord\Parts\Channel\Message;
use Discord\Parts\Embed\Embed;
use Discord\Parts\Interactions\Interaction;

class DefaultCommandImplementationListener
{

    private const AI_IMAGE_HASH = 978323941;

    public static function reaction_giveaway(DiscordBot          $bot,
                                             Interaction|Message $interaction,
                                             object              $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $messages = explode(",", $arguments["messages"]["value"] ?? null);
        $amount = $arguments["amount"]["value"] ?? null;
        $inviteProbabilityDivisor = $arguments["invite-probability-divisor"]["value"] ?? null;

        if (!empty($messages)) {
            $bot->utilities->acknowledgeCommandMessage(
                $interaction,
                MessageBuilder::new()->setContent("Please wait..."),
                true
            )->done($bot->utilities->zeroArgumentFunction(
                function () use ($interaction, $messages, $arguments, $bot, $inviteProbabilityDivisor, $amount) {
                    foreach ($messages as $key => $messageID) {
                        if (!is_numeric($messageID)) {
                            unset($messages[$key]);
                        }
                    }
                    $messageCount = sizeof($messages);

                    if ($messageCount > 0) {
                        $messageFinish = 0;
                        $reactionFinish = 0;
                        $users = array();
                        $callable = $bot->utilities->zeroArgumentFunction(
                            function () use (&$users, $bot, $interaction, $inviteProbabilityDivisor, $amount) {
                                $members = $interaction->guild->members->toArray();

                                foreach ($users as $arrayKey => $user) {
                                    if (!array_key_exists($user->id, $members)) {
                                        unset($users[$arrayKey]);
                                    }
                                }
                                shuffle($users);

                                if (sizeof($users) <= $amount) {
                                    $interaction->updateOriginalResponse(
                                        MessageBuilder::new()->setContent(
                                            DiscordSyntax::LIGHT_CODE_BLOCK .
                                            substr(
                                                "<@" . implode(">\n<@", array_keys($users)) . ">",
                                                0,
                                                DiscordInheritedLimits::MESSAGE_MAX_LENGTH
                                                - strlen(DiscordSyntax::LIGHT_CODE_BLOCK) * 2
                                            )
                                            . DiscordSyntax::LIGHT_CODE_BLOCK
                                        )
                                    );
                                } else {
                                    $winners = array();
                                    $multiplier = array();
                                    $probability = array();

                                    if ($inviteProbabilityDivisor !== null) {
                                        $invites = array();
                                        $totalInvites = 0;

                                        foreach ($users as $user) {
                                            $count = $bot->inviteTracker->getUserStats(
                                                $interaction->guild,
                                                $user->id
                                            )->users_invited;
                                            $invites[$user->id] = $count;
                                            $totalInvites += $count;
                                            $probability[$user->id] = rand(0, 10_000) / 10_000.0;
                                        }
                                        foreach ($invites as $userID => $count) {
                                            $multiplier[$userID] = 1.0 + ($count / (float)$totalInvites / (float)$inviteProbabilityDivisor);
                                        }
                                    } else {
                                        foreach ($users as $user) {
                                            $multiplier[$user->id] = 1.0;
                                            $probability[$user->id] = rand(0, 10_000) / 10_000.0;
                                        }
                                    }

                                    while (true) {
                                        $currentProbability = rand(0, 100) / 100.0;

                                        foreach ($users as $arrayKey => $user) {
                                            if ($probability[$user->id] * $multiplier[$user->id] >= $currentProbability) {
                                                $winners[] = $user->id;
                                                unset($users[$arrayKey]);
                                                $amount--;

                                                if ($amount === 0) {
                                                    break 2;
                                                }
                                            }
                                        }
                                    }

                                    $interaction->updateOriginalResponse(
                                        MessageBuilder::new()->setContent(
                                            DiscordSyntax::LIGHT_CODE_BLOCK .
                                            substr(
                                                "<@" . implode(">\n<@", $winners) . ">",
                                                0,
                                                DiscordInheritedLimits::MESSAGE_MAX_LENGTH
                                                - strlen(DiscordSyntax::LIGHT_CODE_BLOCK) * 2
                                            )
                                            . DiscordSyntax::LIGHT_CODE_BLOCK
                                        )
                                    );
                                }
                            }
                        );

                        foreach ($messages as $messageID) {
                            $interaction->channel->messages->fetch((int)$messageID, true)->done(
                                $bot->utilities->oneArgumentFunction(
                                    function (Message $message)
                                    use ($interaction, &$reactionFinish, &$messageFinish, &$users, &$messageCount, $callable, $bot) {
                                        $reactionCount = $message->reactions->count();

                                        if ($reactionCount > 0) {
                                            foreach ($message->reactions as $reaction) {
                                                $reaction->getAllUsers()->done(
                                                    $bot->utilities->oneArgumentFunction(
                                                        function (mixed $reactionUsers)
                                                        use (
                                                            $interaction, &$users, $reactionCount, &$reactionFinish,
                                                            &$messageFinish, $messageCount, $callable
                                                        ) {
                                                            foreach ($reactionUsers as $user) {
                                                                if (!array_key_exists($user->id, $users)) {
                                                                    $users[$user->id] = $user;
                                                                }
                                                            }
                                                            $reactionFinish++;

                                                            if ($reactionFinish === $reactionCount) {
                                                                $messageFinish++;

                                                                if ($messageFinish === $messageCount) {
                                                                    $callable();
                                                                }
                                                            }
                                                        }
                                                    )
                                                );
                                            }
                                        } else {
                                            $messageCount--;

                                            if ($messageCount === 0) {
                                                $interaction->updateOriginalResponse(
                                                    MessageBuilder::new()->setContent("No reactions found."),
                                                );
                                            }
                                        }
                                    }
                                )
                            );
                        }
                    } else {
                        $interaction->updateOriginalResponse(
                            MessageBuilder::new()->setContent("Invalid message IDs."),
                        );
                    }
                }
            ));
        }
    }

    public static function generate_image_from_image(DiscordBot          $bot,
                                                     Interaction|Message $interaction,
                                                     object              $command): void
    {
        $prompt = $interaction->data->resolved->attachments->first();

        if (empty($prompt)) {
            $bot->utilities->acknowledgeCommandMessage(
                $interaction,
                MessageBuilder::new()->setContent("No image found."),
                true
            );
            return;
        } else if ($prompt->width === null
            || $prompt->height === null
            || $prompt->url === null) {
            $bot->utilities->acknowledgeCommandMessage(
                $interaction,
                MessageBuilder::new()->setContent("Invalid image."),
                true
            );
            return;
        }
        $arguments = $interaction->data->options->toArray();
        $xResolution = $arguments["x-resolution"]["value"] ?? null;
        $yResolution = $arguments["y-resolution"]["value"] ?? null;
        $hd = $arguments["hd"]["value"] ?? false;
        $private = $arguments["private"]["value"] ?? false;

        $bot->utilities->acknowledgeCommandMessage(
            $interaction,
            MessageBuilder::new()->setContent(DiscordAIMessages::INITIAL_PROMPT),
            $private
        )->done($bot->utilities->zeroArgumentFunction(
            function () use ($interaction, $bot, $prompt, $xResolution, $yResolution, $hd) {
                $object2 = new stdClass();
                $object2->url = $prompt->url;

                $object1 = new stdClass();
                $object1->type = "image_url";
                $object1->image_url = $object2;
                $system = "Describe the image at the best of your ability with a maximum character length of 2000.";
                $messages = array(
                    array(
                        "role" => "system",
                        "content" => $system
                    ),
                    array(
                        "role" => "user",
                        "content" => array(
                            $object1
                        )
                    )
                );
                $input = array(
                    "messages" => $messages
                );
                $managerAI = new AIManager(
                    AIImageReadingModelFamily::BEST_PRICE_TO_PERFORMANCE,
                    AIHelper::getAuthorization(AIAuthorization::OPENAI),
                    $input
                );
                $outcome = $managerAI->getResult(
                    self::AI_IMAGE_HASH,
                    [],
                    $system
                );

                if (array_shift($outcome)) {
                    $model = array_shift($outcome);
                    $data = array_shift($outcome);
                    $originalPrompt = $prompt;
                    $prompt = $model->getText($data);

                    if (!empty($prompt)) {
                        $input = array(
                            "n" => 1,
                            "prompt" => $prompt,
                            "size" => $xResolution . "x" . $yResolution,
                        );
                        if ($hd) {
                            $input["quality"] = "hd";
                        }
                        $managerAI = new AIManager(
                            AIImageCreationModelFamily::MOST_POWERFUL,
                            AIHelper::getAuthorization(AIAuthorization::OPENAI),
                            $input
                        );
                        $outcome = $managerAI->getResult(
                            self::AI_IMAGE_HASH
                        );

                        if (array_shift($outcome)) {
                            $image = $outcome[0]->getImage($outcome[1]);
                            $messageBuilder = MessageBuilder::new()->setContent($outcome[0]->getRevisedPrompt($outcome[1]) ?? $prompt);
                            $embed = new Embed($bot->discord);
                            $embed->setImage($originalPrompt->url);
                            $messageBuilder->addEmbed($embed);

                            $embed = new Embed($bot->discord);
                            $embed->setImage($image);
                            $messageBuilder->addEmbed($embed);
                            $interaction->updateOriginalResponse($messageBuilder);
                        } else {
                            $interaction->updateOriginalResponse(
                                MessageBuilder::new()->setContent("Failed to generate image description: " . json_encode($outcome[1]))
                            );
                        }
                    } else {
                        $interaction->updateOriginalResponse(
                            MessageBuilder::new()->setContent("Failed to generate image initial description: " . json_encode($data)),
                        );
                    }
                } else {
                    $interaction->updateOriginalResponse(
                        MessageBuilder::new()->setContent("Failed to generate image: " . json_encode($outcome[1])),
                    );
                }
            }
        ));
    }

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
        $interaction->channel->getMessageHistory(
            [
                'limit' => max(min($pastMessages, 100), 1),
                'cache' => true
            ]
        )->done($bot->utilities->oneArgumentFunction(
            function (mixed $messageHistory)
            use ($interaction, $bot, $prompt, $xResolution, $yResolution, $hd, $pastMessages, $private) {
                $messageHistory = $messageHistory->toArray();

                $bot->utilities->acknowledgeCommandMessage(
                    $interaction,
                    MessageBuilder::new()->setContent(DiscordAIMessages::INITIAL_PROMPT),
                    $private
                )->done($bot->utilities->zeroArgumentFunction(
                    function ()
                    use ($interaction, $bot, $prompt, $xResolution, $yResolution, $hd, $pastMessages, $messageHistory) {
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
                                    || $newPrompt === DiscordAIMessages::INITIAL_PROMPT) {
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
                        $managerAI = new AIManager(
                            AIImageCreationModelFamily::MOST_POWERFUL,
                            AIHelper::getAuthorization(AIAuthorization::OPENAI),
                            $arguments
                        );
                        $outcome = $managerAI->getResult(
                            self::AI_IMAGE_HASH
                        );

                        if (array_shift($outcome)) {
                            $image = $outcome[0]->getImage($outcome[1]);
                            $messageBuilder = MessageBuilder::new()->setContent($outcome[0]->getRevisedPrompt($outcome[1]) ?? $prompt);
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
                    }
                ));
            }
        ));
    }

    public static function create_ticket(DiscordBot          $bot,
                                         Interaction|Message $interaction,
                                         object              $command): void
    {
        // channel.role.user
        $channel = $interaction->data?->resolved?->channels?->first();

        if ($channel !== null
            && ($channel->allowText()
                || $channel->allowInvite()
                || $channel->allowVoice())) {
            $bot->utilities->acknowledgeCommandMessage(
                $interaction,
                MessageBuilder::new()->setContent(
                    "Channel must be a category channel, not text, voice or allow invites."
                ),
                true
            );
            return;
        }
        $arguments = $interaction->data->options->toArray();
        $permissionEquation = 446676978752;

        $rolePermissions = new stdClass();
        $rolePermissions->allow = $permissionEquation;
        $rolePermissions->deny = 0;

        $everyonePermissions = new stdClass();
        $everyonePermissions->allow = 0;
        $everyonePermissions->deny = $permissionEquation;

        $channelName = $arguments["channel-name"]["value"] ?? null;

        if ($channelName !== null) {
            $bot->userTickets->create(
                $interaction,
                null,
                $channel?->id,
                $arguments["channel-name"]["value"],
                null,
                null,
                null,
                array(
                    $interaction->data?->resolved?->roles?->first()?->id => $rolePermissions,
                    $interaction->guild_id => $everyonePermissions
                ),
                null
            );
            $bot->utilities->acknowledgeCommandMessage(
                $interaction,
                MessageBuilder::new()->setContent(
                    "Attempting to create abstract ticket..."
                ),
                true
            );
        } else {
            $bot->utilities->acknowledgeCommandMessage(
                $interaction,
                MessageBuilder::new()->setContent(
                    "Channel name is required."
                ),
                true
            );
        }
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
                    $content .= "__" . $command->id . "__ "
                        . $command->command_placeholder
                        . $command->command_identification . "\n";
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
            DiscordInviteTracker::track($bot, $interaction->guild);
            $object = $bot->inviteTracker->getUserStats(
                $interaction->guild,
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
            DiscordInviteTracker::track($bot, $interaction->guild);
            $array = $bot->inviteTracker->getServerStats(
                $interaction->guild,
            );
            $size = sizeof($array);

            if ($size > 0) {
                $messageBuilder = MessageBuilder::new();
                $counter = 0;
                $unknown = new stdClass();
                $unknown->username = "Unknown";
                $unknown->avatar = null;

                foreach ($array as $object) {
                    $user = $object->user_id === null
                        ? clone $unknown
                        : $bot->utilities->getUser($object->user_id);
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