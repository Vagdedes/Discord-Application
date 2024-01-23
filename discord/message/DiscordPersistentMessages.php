<?php

use Discord\Builders\Components\ActionRow;
use Discord\Builders\Components\Button;
use Discord\Builders\Components\Option;
use Discord\Builders\Components\StringSelect;
use Discord\Builders\MessageBuilder;
use Discord\Parts\Channel\Channel;
use Discord\Parts\Channel\Message;
use Discord\Parts\Interactions\Interaction;
use Discord\Parts\Thread\Thread;

class DiscordPersistentMessages
{
    private DiscordPlan $plan;
    private array $messages;

    public function __construct(DiscordPlan $plan)
    {
        $this->plan = $plan;
        $query = get_sql_query(
            BotDatabaseTable::BOT_CONTROLLED_MESSAGES,
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
            ),
            array(
                "ASC",
                "copy_of"
            )
        );

        if (!empty($query)) {
            $this->process($query, 0);
        }
    }

    public function send(Interaction   $interaction,
                         string|object $key, bool $ephemeral): bool
    {
        $message = $this->messages[$key] ?? null;

        if ($message !== null) {
            $this->plan->utilities->acknowledgeMessage(
                $interaction,
                $this->build($interaction, $message),
                $ephemeral
            );
            return true;
        } else {
            return false;
        }
    }

    public function get(?Interaction $interaction, string|object $key): ?MessageBuilder
    {
        $message = $this->messages[$key] ?? null;

        if ($message !== null) {
            return $this->build($interaction, $message);
        } else {
            return null;
        }
    }

    public function create(Interaction $interaction,
                           string      $message, array $components, bool $ephemeral): bool
    {
        $object = $this->plan->instructions->getObject(
            $interaction->guild,
            $interaction->channel,
            $interaction->message?->thread,
            $interaction->user,
            $interaction->message,
        );
        $messageBuilder = MessageBuilder::new()->setContent(
            $this->plan->instructions->replace(array($message), $object)[0]
        );

        foreach ($components as $component) {
            if ($component instanceof StringSelect) {
                $placeholder = $component->getPlaceholder();

                if ($placeholder !== null) {
                    $component->setPlaceholder(
                        $this->plan->instructions->replace(array($placeholder), $object)[0]
                    );
                    $options = $component->getOptions();

                    foreach ($options as $arrayKey => $option) {
                        $component->removeOption($option);
                        $description = $option->getDescription();
                        $option = Option::new(
                            $option->getLabel(),
                            $option->getValue()
                        )->setDefault(
                            $option->isDefault()
                        )->setEmoji(
                            $option->getEmoji()
                        );

                        if ($description !== null) {
                            $option->setDescription(
                                $this->plan->instructions->replace(array($description), $object)[0]
                            );
                        }
                        $options[$arrayKey] = $option;
                    }
                    foreach ($options as $option) {
                        $component->addOption($option);
                    }
                }
            } else if ($component instanceof ActionRow) {
                foreach ($component->getComponents() as $subComponent) {
                    if ($subComponent instanceof Button) {
                        $label = $subComponent->getLabel();

                        if ($label !== null) {
                            $subComponent->setLabel(
                                $this->plan->instructions->replace(array($label), $object)[0]
                            );
                        }
                    }
                }
            }
            $messageBuilder->addComponent($component);
        }

        $this->plan->utilities->acknowledgeMessage($interaction, $messageBuilder, $ephemeral);
        return true;
    }

    private function build(?Interaction $interaction, object $messageRow): MessageBuilder
    {
        $messageBuilder = $this->plan->utilities->buildMessageFromObject(
            $messageRow,
            $interaction === null ? null :
                $this->plan->instructions->getObject(
                    $interaction->guild,
                    $interaction->channel,
                    $interaction->message?->thread,
                    $interaction->member,
                    $interaction->message
                )
        );

        if ($messageBuilder === null) {
            $messageBuilder = MessageBuilder::new();
        }
        $messageBuilder = $this->plan->component->addButtons(
            $interaction,
            $messageBuilder,
            $messageRow->id,
            false
        );
        $messageBuilder = $this->plan->component->addSelection(
            $interaction,
            $messageBuilder,
            $messageRow->id,
            false
        );
        $messageBuilder = $this->plan->listener->callMessageBuilderCreation(
            $interaction,
            $messageBuilder,
            $messageRow->listener_class,
            $messageRow->listener_method
        );
        return $this->plan->interactionRoles->process($messageBuilder, $messageRow->id);
    }

    private function process(array $array, int $position): void
    {
        $messageRow = $array[$position] ?? null;

        if ($messageRow !== null) {
            if ($messageRow->server_id !== null
                && $messageRow->channel_id !== null) {
                global $logger;
                $channel = $this->plan->bot->discord->getChannel($messageRow->channel_id);

                if ($channel !== null
                    && $channel->guild_id == $messageRow->server_id) {
                    if ($messageRow->thread_id !== null) {
                        $finalChannel = null;

                        foreach ($channel->threads as $thread) {
                            if ($thread instanceof Thread && $messageRow->thread_id == $thread->id) {
                                $finalChannel = $thread;
                                break;
                            }
                        }
                    } else if ($channel->allowText()) {
                        $finalChannel = $channel;
                    } else {
                        $finalChannel = null;
                    }

                    if ($finalChannel !== null) {
                        $oldMessageRow = $messageRow;
                        $custom = $messageRow->copy_of !== null;

                        if ($custom) {
                            $messageRow = $this->messages[$messageRow->copy_of] ?? null;

                            if ($messageRow === null) {
                                $logger->logError(
                                    $this->plan->planID,
                                    "Message {$oldMessageRow->id} is a copy of {$oldMessageRow->copy_of} but it does not exist."
                                );
                                $this->process($array, $position + 1);
                                return;
                            }
                        } else if ($messageRow->name !== null) {
                            $this->messages[$messageRow->name] = $messageRow;
                        }
                        if ($oldMessageRow->message_id === null) {
                            $this->newMessage($finalChannel, $messageRow, $oldMessageRow, $array, $position);
                        } else {
                            $this->editMessage($finalChannel, $custom, $messageRow, $oldMessageRow, $array, $position);
                        }
                    } else {
                        $this->process($array, $position + 1);
                    }
                }
            } else if ($messageRow->name !== null) {
                $this->messages[$messageRow->name] = $messageRow;
                $this->process($array, $position + 1);
            }
        }
    }

    private function newMessage(Thread|Channel $channel,
                                object         $messageRow, object $oldMessageRow,
                                array          $array, int $position): void
    {
        $channel->sendMessage($this->build(null, $messageRow))->done(
            function (Message $message) use ($messageRow, $oldMessageRow, $array, $position) {
                $this->plan->component->addReactions($message, $messageRow->id);
                $messageRow->message_id = $message->id;
                set_sql_query(
                    BotDatabaseTable::BOT_CONTROLLED_MESSAGES,
                    array(
                        "message_id" => $message->id
                    ),
                    array(
                        array("id", $oldMessageRow->id)
                    ),
                    null,
                    1
                );
                $this->process($array, $position + 1);
            }
        );
    }

    private function editMessage(Thread|Channel $channel,
                                 bool           $custom,
                                 object         $messageRow, object $oldMessageRow,
                                 array          $array, int $position): void
    {
        try {
            $channel->messages->fetch($oldMessageRow->message_id)->done(
                function (Message $message) use ($channel, $custom, $messageRow, $oldMessageRow, $array, $position) {
                    $this->plan->component->addReactions($message, $messageRow->id);
                    if ($message->user_id == $this->plan->bot->botID) {
                        if ($custom) {
                            $messageRow->message_id = $message->id;
                        }
                        $message->edit($this->build(null, $messageRow));
                    } else {
                        $message->delete();
                        $this->newMessage($channel, $messageRow, $oldMessageRow, $array, $position);
                    }
                    $this->process($array, $position + 1);
                }
            );
        } catch (Throwable $ignored) {
            $this->process($array, $position + 1);
        }
    }
}