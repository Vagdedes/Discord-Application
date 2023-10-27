<?php

use Discord\Builders\Components\ActionRow;
use Discord\Builders\Components\Button;
use Discord\Builders\Components\Component;
use Discord\Builders\Components\Option;
use Discord\Builders\Components\SelectMenu;
use Discord\Builders\Components\TextInput;
use Discord\Builders\MessageBuilder;
use Discord\Helpers\Collection;
use Discord\Parts\Interactions\Interaction;

class DiscordComponent
{

    private array $listenerObjects;

    private DiscordPlan $plan;

    public function __construct(DiscordPlan $plan)
    {
        $this->plan = $plan;
        $this->listenerObjects = array();
        clear_memory(array(self::class), true);
    }

    public function clear(): void
    {
        if (!empty($this->listenerObjects)) {
            foreach ($this->listenerObjects as $listenerObject) {
                $listenerObject->removeListener();
            }
        }
    }

    // Separator

    public function showModal(Interaction $interaction, string|object $key): bool
    {
        set_sql_cache();
        $query = get_sql_query(
            BotDatabaseTable::BOT_MODAL_COMPONENTS,
            null,
            array(
                array("deletion_date", null),
                array("name", $key),
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
            set_sql_cache();
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
                    $interaction->guild_id,
                    $interaction->guild->name,
                    $interaction->channel_id,
                    $interaction->channel->name,
                    $interaction->message?->thread?->id,
                    $interaction->message?->thread,
                    $interaction->user->id,
                    $interaction->user->username,
                    $interaction->user->displayname,
                    $interaction->message->content,
                    $interaction->message->id,
                    $this->plan->discord->user->id
                );

                foreach ($subQuery as $textInput) {
                    $placeholder = $this->plan->instructions->replace(array($textInput->placeholder), $object)[0];
                    $input = TextInput::new(
                        $placeholder,
                        $textInput->allow_lines !== null ? TextInput::STYLE_PARAGRAPH : TextInput::STYLE_SHORT,
                        $textInput->custom_id
                    )->setRequired(
                        $textInput->required !== null
                    )->setPlaceholder(
                        $placeholder
                    );

                    if ($textInput->value) {
                        $input->setValue(
                            $this->plan->instructions->replace(array($textInput->value), $object)[0]
                        );
                    }
                    if ($textInput->min_length !== null) {
                        $input->setMinLength($textInput->min_length);
                    }
                    if ($textInput->max_length !== null) {
                        $input->setMaxLength($textInput->max_length);
                    }
                    $actionRow = ActionRow::new();
                    $actionRow->addComponent($input);
                }
                $interaction->showModal(
                    $query->title,
                    $query->custom_id,
                    $query->subComponents,
                    function (Interaction $interaction, Collection $components) use ($query, $object) {
                        if ($query->response !== null) {
                            $interaction->acknowledgeWithResponse($query->ephemeral !== null);
                            $interaction->updateOriginalResponse(MessageBuilder::new()->setContent(
                                $this->plan->instructions->replace(array($query->response), $object)[0]
                            ));
                        } else {
                            $this->plan->listener->callImplementation(
                                $interaction,
                                $query->listener_class,
                                $query->listener_method,
                                $components
                            );
                        }
                    }
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
        $interaction->showModal(
            $title,
            $customID,
            $textInputs,
            $listener
        );
        return true;
    }

    // Separator

    public function addButtons(MessageBuilder $messageBuilder,
                               int|string     $componentID): MessageBuilder
    {
        set_sql_cache();
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
            ),
            DiscordProperties::MAX_BUTTONS_PER_ACTION_ROW
        );

        if (!empty($query)) {
            global $logger;
            $rows = array();

            foreach ($query as $buttonObject) {
                if (array_key_exists($buttonObject->row_id, $rows)) {
                    $rows[$buttonObject->row_id][] = $buttonObject;
                } else {
                    $rows[$buttonObject->row_id] = array($buttonObject);
                }
            }
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
                        case "grey":
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

                        if (!$button->isDisabled()) {
                            $button->setListener(function (Interaction $interaction) use ($actionRow, $button, $buttonObject) {
                                if (!$this->hasCooldown($actionRow)) {
                                    $this->extract($interaction, $buttonObject, $button);
                                    $this->listenerObjects[] = $button;
                                }
                            }, $this->plan->discord);
                        }
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
                    if (!$this->hasCooldown($actionRow)) {
                        $listener($interaction, $button);
                        $this->listenerObjects[] = $button;
                    }
                }, $this->plan->discord);
            }
        }
        return $actionRow;
    }

    // Separator

    public function addSelection(MessageBuilder $messageBuilder,
                                 int|string     $componentID): MessageBuilder
    {
        set_sql_cache();
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
            set_sql_cache();
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
                    $choice = Option::new($choiceObject->name)
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

                if (!$select->isDisabled()) {
                    $select->setListener(function (Interaction $interaction, Collection $options)
                    use ($query, $select) {
                        if (!$this->hasCooldown($select)) {
                            $this->extract($interaction, $query, $options);
                            $this->listenerObjects[] = $select;
                        }
                    }, $this->plan->discord);
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
                if (!$this->hasCooldown($select)) {
                    $listener($interaction, $options);
                    $this->listenerObjects[] = $select;
                }
            }, $this->plan->discord);
        }
        return $select;
    }

    // Separator

    private function extract(Interaction $interaction,
                             object      $databaseObject, mixed $objects = null): void
    {
        if ($databaseObject->response !== null) {
            $interaction->respondWithMessage(
                MessageBuilder::new()->setContent(
                    $this->plan->instructions->replace(
                        array($databaseObject->response),
                        $this->plan->instructions->getObject(
                            $interaction->guild_id,
                            $interaction->guild->name,
                            $interaction->channel_id,
                            $interaction->channel->name,
                            $interaction->message?->thread?->id,
                            $interaction->message?->thread,
                            $interaction->user->id,
                            $interaction->user->username,
                            $interaction->user->displayname,
                            $interaction->message->content,
                            $interaction->message->id,
                            $this->plan->discord->user->id
                        )
                    )[0]
                ),
                $databaseObject->ephemeral !== null
            );
        } else {
            $this->plan->listener->callImplementation(
                $interaction,
                $databaseObject->listener_class,
                $databaseObject->listener_method,
                $objects
            );
        }
    }

    private function hasCooldown(Component $component): bool
    {
        return has_memory_cooldown(array(
            self::class,
            "interaction",
            json_encode($component)
        ), 1);
    }
}