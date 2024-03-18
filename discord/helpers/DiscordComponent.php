<?php

use Discord\Builders\Components\ActionRow;
use Discord\Builders\Components\Button;
use Discord\Builders\Components\Component;
use Discord\Builders\Components\Option;
use Discord\Builders\Components\SelectMenu;
use Discord\Builders\Components\TextInput;
use Discord\Builders\MessageBuilder;
use Discord\Helpers\Collection;
use Discord\Parts\Channel\Message;
use Discord\Parts\Embed\Embed;
use Discord\Parts\Interactions\Interaction;
use Discord\Parts\WebSockets\MessageReaction;

class DiscordComponent
{
    private DiscordPlan $plan;
    private array $reactions;

    public function __construct(DiscordPlan $plan)
    {
        $this->plan = $plan;
        $this->reactions = array();
    }

    // Separator

    public function showModal(Interaction $interaction, int|string $key,
                              ?callable   $customListener = null): bool
    {
        set_sql_cache(null, self::class);
        $query = get_sql_query(
            BotDatabaseTable::BOT_MODAL_COMPONENTS,
            null,
            array(
                array("deletion_date", null),
                array(is_numeric($key) ? "id" : "name", $key),
                null,
                array("expiration_date", "IS", null, 0),
                array("expiration_date", ">", get_current_date()),
                null
            ),
            null,
            1
        );

        if (!empty($query)) {
            $query = $query[0];
            set_sql_cache(null, self::class);
            $subQuery = get_sql_query(
                BotDatabaseTable::BOT_MODAL_SUB_COMPONENTS,
                null,
                array(
                    array("deletion_date", null),
                    array("component_id", $query->id),
                    null,
                    array("expiration_date", "IS", null, 0),
                    array("expiration_date", ">", get_current_date()),
                    null
                ),
                array(
                    "DESC",
                    "priority"
                )
            );

            if (!empty($subQuery)) {
                $object = $this->plan->instructions->getObject(
                    $interaction->guild,
                    $interaction->channel,
                    $interaction->user,
                    $interaction->message,
                );

                foreach ($subQuery as $arrayKey => $textInput) {
                    $input = TextInput::new(
                        $this->plan->instructions->replace(array($textInput->title), $object)[0],
                        $textInput->allow_lines !== null ? TextInput::STYLE_PARAGRAPH : TextInput::STYLE_SHORT,
                        $textInput->custom_id
                    )->setRequired(
                        $textInput->required !== null
                    )->setPlaceholder(
                        $this->plan->instructions->replace(array($textInput->placeholder), $object)[0]
                    );

                    if ($textInput->default_value) {
                        $input->setValue(
                            $this->plan->instructions->replace(array($textInput->default_value), $object)[0]
                        );
                    }
                    if ($textInput->min_length !== null) {
                        $input->setMinLength($textInput->min_length);
                    }
                    if ($textInput->max_length !== null) {
                        $input->setMaxLength($textInput->max_length);
                    }
                    $input = $this->plan->listener->callModalCreation(
                        $interaction,
                        $input,
                        $arrayKey,
                        $query->creation_listener_class,
                        $query->creation_listener_method
                    );
                    $actionRow = ActionRow::new();
                    $actionRow->addComponent($input);
                    $subQuery[$arrayKey] = $actionRow;
                }
                if ($query->custom_id === null) {
                    global $min_59bit_Integer, $max_59bit_Integer;
                    $query->custom_id = rand($min_59bit_Integer, $max_59bit_Integer);
                }
                if ($customListener === null) {
                    $customListener = function (Interaction $interaction, Collection $components) use ($query, $object) {
                        if ($query->response !== null) {
                            $embed = new Embed($this->plan->bot->discord);
                            $embed->setDescription($this->plan->instructions->replace(array($query->response), $object)[0]);
                            $this->plan->utilities->acknowledgeMessage(
                                $interaction,
                                MessageBuilder::new()->addEmbed($embed),
                                $query->ephemeral !== null
                            );
                        } else {
                            $this->plan->listener->callModalImplementation(
                                $interaction,
                                $query->implement_listener_class,
                                $query->implement_listener_method,
                                $components
                            );
                        }
                    };
                }
                $interaction->showModal(
                    $query->title,
                    $query->custom_id,
                    $subQuery,
                    $customListener
                );
                return true;
            } else {
                global $logger;
                $logger->logError(
                    $this->plan->planID,
                    "Invalid modal with ID: " . $key,
                );
                return false;
            }
        } else {
            return false;
        }
    }

    public function createModal(Interaction $interaction,
                                string      $title, array $textInputs,
                                ?int        $customID = null,
                                ?callable   $listener = null): bool
    {
        foreach ($textInputs as $arrayKey => $textInput) {
            $actionRow = ActionRow::new();
            $actionRow->addComponent($textInput);
            $textInputs[$arrayKey] = $actionRow;
        }
        if ($customID === null) {
            global $min_59bit_Integer, $max_59bit_Integer;
            $customID = rand($min_59bit_Integer, $max_59bit_Integer);
        }
        $interaction->showModal(
            $title,
            $customID,
            $textInputs,
            $listener
        );
        return true;
    }

    // Separator

    public function addReactions(Message    $message,
                                 int|string $componentID): void
    {
        set_sql_cache(null, self::class);
        $query = get_sql_query(
            BotDatabaseTable::BOT_REACTION_COMPONENTS,
            null,
            array(
                array(is_numeric($componentID) ? "controlled_message_id" : "name", $componentID),
                array("deletion_date", null),
                null,
                array("expiration_date", "IS", null, 0),
                array("expiration_date", ">", get_current_date()),
                null
            ),
            array(
                "DESC",
                "priority"
            )
        );

        if (!empty($query)) {
            $this->reactions[$message->id] = array();

            foreach ($query as $row) {
                $this->reactions[$message->id][$row->emoji] = $row;
                $message->react($row->emoji);
            }
        }
    }

    public function handleReaction(MessageReaction $reaction): void
    {
        if ($reaction->member->id != $this->plan->bot->botID) {
            $reactionObjects = $this->reactions[$reaction->message_id] ?? null;

            if (!empty($reactionObjects)) {
                $reactionObject = $reactionObjects[$reaction->emoji->name] ?? null;

                if ($reactionObject !== null) {
                    $this->plan->listener->callReactionCreation(
                        $reaction,
                        $reactionObject->listener_class,
                        $reactionObject->listener_method,
                        $reactionObject->controlled_message_id
                    );
                }
            }
        }
    }

    // Separator

    public function addButtons(?Interaction   $interaction,
                               MessageBuilder $messageBuilder,
                               int|string     $componentID,
                               bool           $listener = true): MessageBuilder
    {
        set_sql_cache(null, self::class);
        $query = get_sql_query(
            BotDatabaseTable::BOT_BUTTON_COMPONENTS,
            null,
            array(
                array(is_numeric($componentID) ? "controlled_message_id" : "name", $componentID),
                array("deletion_date", null),
                null,
                array("expiration_date", "IS", null, 0),
                array("expiration_date", ">", get_current_date()),
                null
            ),
            array(
                "DESC",
                "priority"
            )
        );

        if (!empty($query)) {
            global $logger;
            $rows = array();

            foreach ($query as $buttonObject) {
                if (array_key_exists($buttonObject->row_id, $rows)) {
                    $rows[$buttonObject->row_id][] = $buttonObject;

                    if (count($rows[$buttonObject->row_id]) > DiscordInheritedLimits::MAX_BUTTONS_PER_ACTION_ROW) {
                        $logger->logError(
                            $this->plan->planID,
                            "More than allowed buttons per row in button with ID: " . $buttonObject->id,
                        );
                        break;
                    }
                } else {
                    $rows[$buttonObject->row_id] = array($buttonObject);
                }
            }
            ksort($rows);

            foreach ($rows as $row) {
                $actionRow = ActionRow::new();

                foreach ($row as $buttonObject) {
                    switch ($buttonObject->color) {
                        case "red":
                            $button = Button::new(Button::STYLE_DANGER)->setLabel(
                                $buttonObject->label
                            )->setDisabled(
                                $buttonObject->disabled !== null
                            );
                            break;
                        case "green":
                            $button = Button::new(Button::STYLE_SUCCESS)
                                ->setLabel(
                                    $buttonObject->label
                                )->setDisabled(
                                    $buttonObject->disabled !== null
                                );
                            break;
                        case "blue":
                            $button = Button::new(Button::STYLE_PRIMARY)
                                ->setLabel(
                                    $buttonObject->label
                                )->setDisabled(
                                    $buttonObject->disabled !== null
                                );
                            break;
                        case "gray":
                            $button = Button::new(Button::STYLE_SECONDARY)
                                ->setLabel(
                                    $buttonObject->label
                                )->setDisabled(
                                    $buttonObject->disabled !== null
                                );
                            break;
                        default:
                            if ($buttonObject->url !== null) {
                                $button = Button::new(Button::STYLE_LINK)
                                    ->setLabel(
                                        $buttonObject->label
                                    )->setDisabled(
                                        $buttonObject->disabled !== null
                                    )->setUrl(
                                        $buttonObject->url
                                    );
                            } else {
                                $button = null;
                                $logger->logError(
                                    $this->plan->planID,
                                    "Invalid button with ID: " . $buttonObject->id,
                                );
                            }
                            break;
                    }
                    if ($button !== null) {
                        if ($buttonObject->emoji !== null) {
                            $button->setEmoji($buttonObject->emoji);
                        }
                        if ($buttonObject->custom_id !== null) {
                            $button->setCustomId($buttonObject->custom_id);
                        }
                        $actionRow->addComponent($button);

                        $this->plan->instructions->manager->addExtra(
                            "message-button" . $componentID,
                            $button->jsonSerialize()
                        );
                        if (!$button->isDisabled() && $button->getStyle() !== Button::STYLE_LINK) {
                            $button->setListener(function (Interaction $interaction)
                            use ($actionRow, $button, $buttonObject, $messageBuilder) {
                                $this->extract($interaction, $messageBuilder, $buttonObject, $button);
                            }, $this->plan->bot->discord);
                        }
                    }
                    if ($listener) {
                        $messageBuilder = $this->plan->listener->callMessageBuilderCreation(
                            $interaction,
                            $messageBuilder,
                            $buttonObject->creation_listener_class,
                            $buttonObject->creation_listener_method
                        );
                    }
                }
                $messageBuilder->addComponent($actionRow);
            }
        }
        return $messageBuilder;
    }

    public function makeButtonRow(array $buttonsAndListeners): Component
    {
        $actionRow = ActionRow::new();

        foreach ($buttonsAndListeners as $button => $listener) {
            $actionRow->addComponent($button);

            if ($listener !== null && !$button->isDisabled()) {
                $button->setListener(function (Interaction $interaction) use ($listener, $actionRow, $button) {
                    $listener($interaction, $button);
                }, $this->plan->bot->discord);
            }
        }
        return $actionRow;
    }

    // Separator

    public function addSelection(?Interaction   $interaction,
                                 MessageBuilder $messageBuilder,
                                 int|string     $componentID,
                                 bool           $listener = true): MessageBuilder
    {
        set_sql_cache(null, self::class);
        $query = get_sql_query(
            BotDatabaseTable::BOT_SELECTION_COMPONENTS,
            null,
            array(
                array(is_numeric($componentID) ? "controlled_message_id" : "name", $componentID),
                array("deletion_date", null),
                null,
                array("expiration_date", "IS", null, 0),
                array("expiration_date", ">", get_current_date()),
                null
            ),
            null,
            1
        );

        if (!empty($query)) {
            $query = $query[0];
            set_sql_cache(null, self::class);
            $subQuery = get_sql_query(
                BotDatabaseTable::BOT_SELECTION_SUB_COMPONENTS,
                null,
                array(
                    array("deletion_date", null),
                    array("component_id", $query->id),
                    null,
                    array("expiration_date", "IS", null, 0),
                    array("expiration_date", ">", get_current_date()),
                    null
                ),
                array(
                    "DESC",
                    "priority"
                )
            );

            if (!empty($subQuery)) {
                $select = SelectMenu::new()
                    ->setDisabled($query->disabled !== null);

                if ($query->max_choices !== null) {
                    $select->setMaxValues($query->max_choices);
                }
                if ($query->min_choices !== null) {
                    $select->setMinValues($query->min_choices);
                }
                if ($query->placeholder !== null) {
                    $select->setPlaceholder($query->placeholder);
                }
                foreach ($subQuery as $choiceObject) {
                    $choice = Option::new($choiceObject->name, $choiceObject->value)
                        ->setDefault($choiceObject->default !== null);

                    if ($choiceObject->description !== null) {
                        $choice->setDescription($choiceObject->description);
                    }
                    if ($choiceObject->emoji !== null) {
                        $choice->setEmoji($choiceObject->emoji);
                    }
                    $select->addOption($choice);
                }
                $messageBuilder->addComponent($select);

                $this->plan->instructions->manager->addExtra(
                    "message-selection" . $componentID,
                    $select->jsonSerialize()
                );
                if ($listener) {
                    $messageBuilder = $this->plan->listener->callMessageBuilderCreation(
                        $interaction,
                        $messageBuilder,
                        $query->creation_listener_class,
                        $query->creation_listener_method
                    );
                }
                if (!$select->isDisabled()) {
                    $select->setListener(function (Interaction $interaction, Collection $options)
                    use ($query, $select, $messageBuilder) {
                        $this->extract($interaction, $messageBuilder, $query, $options);
                    }, $this->plan->bot->discord);
                }
            } else {
                global $logger;
                $logger->logError(
                    $this->plan->planID,
                    "Invalid selection with ID: " . $componentID,
                );
            }
        }
        return $messageBuilder;
    }

    public function makeSelection(array     $choices,
                                  ?string   $placeholder = null,
                                  bool      $disabled = false,
                                  ?int      $maxChoices = null, ?int $minChoices = null,
                                  ?callable $listener = null): Component
    {
        $select = SelectMenu::new()
            ->setDisabled($disabled);

        if ($maxChoices !== null) {
            $select->setMaxValues($maxChoices);
        }
        if ($minChoices !== null) {
            $select->setMinValues($minChoices);
        }
        if ($placeholder !== null) {
            $select->setPlaceholder($placeholder);
        }
        foreach ($choices as $choice) {
            $select->addOption($choice);
        }
        if ($listener !== null && !$select->isDisabled()) {
            $select->setListener(function (Interaction $interaction, Collection $options) use ($listener, $select) {
                $listener($interaction, $options);
            }, $this->plan->bot->discord);
        }
        return $select;
    }

    // Separator

    private function extract(Interaction    $interaction,
                             MessageBuilder $messageBuilder,
                             object         $databaseObject, mixed $objects = null): void
    {
        if ($databaseObject->response !== null) {
            $embed = new Embed($this->plan->bot->discord);
            $embed->setDescription(
                $this->plan->instructions->replace(array($databaseObject->response),
                    $this->plan->instructions->getObject(
                        $interaction->guild,
                        $interaction->channel,
                        $interaction->user,
                        $interaction->message,
                    ))[0]
            );
            $this->plan->utilities->acknowledgeMessage(
                $interaction,
                MessageBuilder::new()->addEmbed($embed),
                $databaseObject->ephemeral !== null
            );
        } else {
            $this->plan->listener->callMessageImplementation(
                $interaction,
                $messageBuilder,
                $databaseObject->implement_listener_class,
                $databaseObject->implement_listener_method,
                $objects
            );
        }
    }
}