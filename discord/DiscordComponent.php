<?php

use Discord\Builders\Components\ActionRow;
use Discord\Builders\Components\Button;
use Discord\Builders\Components\Option;
use Discord\Builders\Components\SelectMenu;
use Discord\Builders\Components\TextInput;
use Discord\Builders\MessageBuilder;
use Discord\Discord;
use Discord\Helpers\Collection;
use Discord\Parts\Interactions\Interaction;

class DiscordComponent
{

    private array $modalComponents, $listenerObjects;

    private DiscordPlan $plan;

    public function __construct(DiscordPlan $plan)
    {
        $this->plan = $plan;
        $query = get_sql_query(
            BotDatabaseTable::BOT_MODAL_COMPONENTS,
            null,
            array(
                array("deletion_date", null),
                null,
                array("plan_id", "IS", null, 0),
                array("plan_id", $this->plan->planID),
                null,
                null,
                array("expiration_date", "IS", null, 0),
                array("expiration_date", ">", get_current_date()),
                null
            )
        );

        if (!empty($query)) {
            foreach ($query as $row) {
                $subQuery = get_sql_query(
                    BotDatabaseTable::BOT_MODAL_SUB_COMPONENTS,
                    null,
                    array(
                        array("deletion_date", null),
                        array("component_id", $row->id),
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
                    $object = new stdClass();
                    $object->component = $row;
                    $object->subComponents = array();

                    foreach ($subQuery as $subRow) {
                        $object->subComponents[] = $subRow;
                    }
                    $this->modalComponents[$row->name] = $object;
                }
            }
        }
    }

    public function clear(): void
    {
        if (!empty($this->listenerObjects)) {
            foreach ($this->listenerObjects as $listenerObject) {
                $listenerObject->removeListener();
            }
        }
    }

    public function getModalComponent(Discord       $discord, Interaction $interaction,
                                      string|object $key): ?object
    {
        $modal = $this->modalComponents[$key] ?? null;

        if ($modal !== null) {
            $modal->object = $this->plan->instructions->getObject(
                $interaction->guild_id,
                $interaction->guild->name,
                $interaction->channel_id,
                $interaction->channel->name,
                $interaction->message->thread->id,
                $interaction->message->thread,
                $interaction->user->id,
                $interaction->user->username,
                $interaction->user->displayname,
                $interaction->message->content,
                $interaction->message->id,
                $discord->user->id
            );
            foreach ($modal->subComponents as $arrayKey => $textInput) {
                $placeholder = $this->plan->instructions->replace(array($textInput->placeholder), $modal->object)[0];
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
                        $this->plan->instructions->replace(array($textInput->value), $modal->object)[0]
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
                $modal->subComponents[$arrayKey] = $actionRow;
            }
            return $modal;
        } else {
            return null;
        }
    }

    public function showModal(Discord       $discord, Interaction $interaction,
                              string|object $key): void
    {
        $modal = is_object($key) ? $key : $this->getModalComponent($discord, $interaction, $key);

        if ($modal !== null) {
            $interaction->showModal(
                $modal->title,
                $modal->custom_id,
                $modal->subComponents,
                function (Interaction $interaction, Collection $components) use ($discord, $modal) {
                    if ($modal->response !== null) {
                        $interaction->acknowledgeWithResponse($modal->ephemeral !== null);
                        $interaction->updateOriginalResponse(MessageBuilder::new()->setContent(
                            $this->plan->instructions->replace(array($modal->response), $modal->object)[0]
                        ));
                    } else {
                        $interaction->acknowledge();
                    }
                    $this->plan->listener->call(
                        $discord,
                        $interaction,
                        $modal->listener_class,
                        $modal->listener_method,
                        $components
                    );
                }
            );
        }
    }

    public function addButtons(Discord    $discord, MessageBuilder $messageBuilder,
                               int|string $componentID, bool $cache = true): MessageBuilder
    {
        if ($cache) {
            set_sql_cache(DiscordProperties::SYSTEM_REFRESH_MILLISECONDS);
        }
        $query = get_sql_query(
            BotDatabaseTable::BOT_BUTTON_COMPONENTS,
            null,
            array(
                array("controlled_message_id", $componentID),
                array("deletion_date", null),
                null,
                array("plan_id", "IS", null, 0),
                array("plan_id", $this->plan->planID),
                null,
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
            $actionRow = ActionRow::new();

            foreach ($query as $buttonObject) {
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
                    $actionRow->addComponent($button);
                    $button->setListener(function (Interaction $interaction)
                    use ($discord, $button, $buttonObject) {
                        $this->extract($discord, $interaction, $buttonObject);
                        $this->listenerObjects[] = $button;
                    }, $discord);
                }
            }
            $messageBuilder->addComponent($actionRow);
        }
        return $messageBuilder;
    }

    public function addSelections(Discord    $discord, MessageBuilder $messageBuilder,
                                  int|string $componentID, bool $cache = true): MessageBuilder
    {
        if ($cache) {
            set_sql_cache(DiscordProperties::SYSTEM_REFRESH_MILLISECONDS);
        }
        $query = get_sql_query(
            BotDatabaseTable::BOT_SELECTION_COMPONENTS,
            null,
            array(
                array("controlled_message_id", $componentID),
                array("deletion_date", null),
                null,
                array("plan_id", "IS", null, 0),
                array("plan_id", $this->plan->planID),
                null,
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
                $select->setListener(function (Interaction $interaction, Collection $options)
                use ($discord, $query, $select) {
                    $this->extract($discord, $interaction, $query, $options);
                    $this->listenerObjects[] = $select;
                }, $discord);
            }
        }
        return $messageBuilder;
    }

    private function extract(Discord $discord, Interaction $interaction,
                             object   $databaseObject, mixed $objects = null): void
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
                            $interaction->message->thread->id,
                            $interaction->message->thread,
                            $interaction->user->id,
                            $interaction->user->username,
                            $interaction->user->displayname,
                            $interaction->message->content,
                            $interaction->message->id,
                            $discord->user->id
                        )
                    )[0]
                ),
                $databaseObject->ephemeral !== null
            );
        }
        $this->plan->listener->call(
            $discord,
            $interaction,
            $databaseObject->listener_class,
            $databaseObject->listener_method,
            $objects
        );
    }
}