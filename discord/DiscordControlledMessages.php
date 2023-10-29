<?php

use Discord\Builders\Components\ActionRow;
use Discord\Builders\Components\Button;
use Discord\Builders\Components\Option;
use Discord\Builders\Components\StringSelect;
use Discord\Builders\MessageBuilder;
use Discord\Helpers\Collection;
use Discord\Parts\Channel\Message;
use Discord\Parts\Interactions\Interaction;

class DiscordControlledMessages
{
    private DiscordPlan $plan;
    private array $messages;

    public function __construct(DiscordPlan $plan)
    {
        $this->plan = $plan;
        $this->messages = get_sql_query(
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
            )
        );

        if (!empty($this->messages)) {
            foreach ($this->messages as $arrayKey => $messageRow) {
                if ($messageRow->server_id !== null
                    && $messageRow->channel_id !== null) {
                    $channel = $this->plan->discord->getChannel($messageRow->channel_id);

                    if ($channel !== null
                        && $channel->guild_id == $messageRow->server_id) {
                        if ($messageRow->message_id === null) {
                            $channel->sendMessage($this->build($messageRow))->done(
                                function (Message $message) use ($messageRow) {
                                    $messageRow->message_id = $message->id;
                                    set_sql_query(
                                        BotDatabaseTable::BOT_CONTROLLED_MESSAGES,
                                        array(
                                            "message_id" => $message->id
                                        ),
                                        array(
                                            array("id", $messageRow->id)
                                        ),
                                        null,
                                        1
                                    );
                                }
                            );
                        } else {
                            $channel->getMessageHistory([
                                'limit' => 10,
                            ])->done(function (Collection $messages) use ($messageRow) {
                                foreach ($messages as $message) {
                                    if ($message->user_id == $this->plan->botID
                                        && $message->id == $messageRow->message_id) {
                                        $message->edit($this->build($messageRow));
                                    }
                                }
                            });
                        }
                    }
                }
                unset($this->messages[$arrayKey]);
                $this->messages[$messageRow->name] = $messageRow;
            }
        }
    }

    public function send(Interaction   $interaction,
                         string|object $key, bool $ephemeral): bool
    {
        $message = is_object($key) ? $key : ($this->messages[$key] ?? null);

        if ($message !== null) {
            $interaction->respondWithMessage($this->build($message), $ephemeral);
            return true;
        } else {
            return false;
        }
    }

    public function create(Interaction $interaction,
                           string      $message, array $components, bool $ephemeral): bool
    {
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
        $interaction->respondWithMessage($messageBuilder, $ephemeral);
        return true;
    }

    private function build(object $messageRow): MessageBuilder
    {
        $messageBuilder = MessageBuilder::new()->setContent(
            empty($messageRow->message_content) ? ""
                : $messageRow->message_content
        );
        $messageBuilder = $this->plan->component->addButtons(
            $messageBuilder,
            $messageRow->id
        );
        $messageBuilder = $this->plan->component->addSelection(
            $messageBuilder,
            $messageRow->id
        );
        return $this->plan->listener->callMessageBuilderCreation(
            $messageBuilder,
            $messageRow->listener_class,
            $messageRow->listener_method
        );
    }
}