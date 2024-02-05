<?php

use Discord\Builders\Components\ActionRow;
use Discord\Builders\Components\Button;
use Discord\Builders\Components\Option;
use Discord\Builders\Components\SelectMenu;
use Discord\Builders\MessageBuilder;
use Discord\Helpers\Collection;
use Discord\Parts\Interactions\Interaction;

class DiscordInteractionRoles
{
    private DiscordPlan $plan;
    private array $interactions;

    public function __construct(DiscordPlan $plan)
    {
        $this->plan = $plan;
        $this->interactions = array();
        $query = get_sql_query(
            BotDatabaseTable::BOT_INTERACTION_ROLES,
            null,
            array(
                array("deletion_date", null),
                array("plan_id", $this->plan->planID),
                null,
                array("expiration_date", "IS", null, 0),
                array("expiration_date", ">", get_current_date()),
                null
            )
        );

        if (!empty($query)) {
            global $logger;
            $min = min(
                DiscordInheritedLimits::MAX_BUTTONS_PER_ACTION_ROW * DiscordInheritedLimits::MAX_ACTION_ROWS_PER_MESSAGE,
                DiscordInheritedLimits::MAX_ARGUMENTS_PER_COMMAND
            );

            foreach ($query as $row) {
                $childQuery = get_sql_query(
                    BotDatabaseTable::BOT_INTERACTION_ROLE_CHOICES,
                    null,
                    array(
                        array("deletion_date", null),
                        array("interaction_role_id", $row->id),
                        null,
                        array("expiration_date", "IS", null, 0),
                        array("expiration_date", ">", get_current_date()),
                        null
                    ),
                    array(
                        "DESC",
                        "priority"
                    ),
                    $min
                );

                if (!empty($childQuery)) {
                    $row->choices = $childQuery;
                    $this->interactions[$row->controlled_message_id] = $row;
                } else {
                    $logger->logError(
                        $this->plan->planID,
                        "Found no choices in interaction role with ID: " . $row->id
                    );
                }
            }
        }
    }

    public function process(MessageBuilder $messageBuilder, int|string $controlledMessageID): MessageBuilder
    {
        $object = $this->interactions[$controlledMessageID] ?? null;

        if ($object !== null) {
            global $logger;

            switch ($object->type) {
                case "button":
                    $originalMessageBuilder = $messageBuilder;

                    foreach (array_chunk($object->choices, 5) as $chunk) {
                        $actionRow = ActionRow::new();

                        foreach ($chunk as $choice) {
                            switch ($choice->color) {
                                case "red":
                                    $button = Button::new(Button::STYLE_DANGER)->setLabel(
                                        $choice->name
                                    )->setCustomId($choice->id);
                                    break;
                                case "green":
                                    $button = Button::new(Button::STYLE_SUCCESS)->setLabel(
                                        $choice->name
                                    )->setCustomId($choice->id);
                                    break;
                                case "blue":
                                    $button = Button::new(Button::STYLE_PRIMARY)->setLabel(
                                        $choice->name
                                    )->setCustomId($choice->id);
                                    break;
                                case "gray":
                                    $button = Button::new(Button::STYLE_SECONDARY)->setLabel(
                                        $choice->name
                                    )->setCustomId($choice->id);
                                    break;
                                default:
                                    $logger->logError(
                                        $this->plan->planID,
                                        "Unknown interaction role choice color with ID: " . $choice->id
                                    );
                                    return $originalMessageBuilder;
                            }
                            $button->setListener(function (Interaction $interaction) use ($choice, $button) {
                                $this->toggleRole(
                                    $interaction,
                                    $choice
                                );
                            }, $this->plan->bot->discord);
                            $actionRow->addComponent($button);
                        }
                        $messageBuilder->addComponent($actionRow);
                    }
                    break;
                case "select":
                    $select = SelectMenu::new()
                        ->setMinValues(1)
                        ->setMaxValues(1);

                    if ($object->placeholder !== null) {
                        $select->setPlaceholder($object->placeholder);
                    }
                    foreach ($object->choices as $arrayKey => $choice) {
                        $select->addOption(Option::new($choice->name, $arrayKey)->setDescription(
                            $choice->description
                        ));
                    }
                    $select->setListener(function (Interaction $interaction, Collection $options)
                    use ($select, $object) {
                        $choice = $object->choices[$options[0]->getValue()];
                        $this->toggleRole(
                            $interaction,
                            $choice
                        );
                    }, $this->plan->bot->discord);
                    $messageBuilder->addComponent($select);
                    break;
                default:
                    global $logger;
                    $logger->logError(
                        $this->plan->planID,
                        "Unknown interaction role type with ID: " . $object->id
                    );
                    break;
            }
        }
        return $messageBuilder;
    }

    private function toggleRole(Interaction $interaction, object $choice): void
    {
        $interaction->acknowledge()->done(function () use ($interaction, $choice) {
            $role = $interaction->guild->roles->toArray();
            $role = $role[$choice->role_id] ?? null;

            if ($role !== null) {
                $add = !$this->plan->permissions->hasRole($interaction->member, $choice->role_id);
                $promise = $add
                    ? $interaction->member->addRole($role)
                    : $interaction->member->removeRole($role);

                $promise->done(function () use ($interaction, $add, $choice) {
                    $interaction->sendFollowUpMessage(
                        MessageBuilder::new()->setContent(
                            $choice->reply_success
                        ),
                        true
                    );
                    sql_insert(
                        BotDatabaseTable::BOT_INTERACTION_ROLE_TRACKING,
                        array(
                            "choice_id" => $choice->id,
                            "user_id" => $interaction->member->id,
                            "role_id" => $choice->role_id,
                            "add" => $add,
                            "creation_date" => get_current_date()
                        )
                    );
                });
            } else {
                $interaction->sendFollowUpMessage(
                    MessageBuilder::new()->setContent(
                        $choice->reply_failure
                    ),
                    true
                );
            }
        });
    }
}