<?php

use Discord\Builders\Components\ActionRow;
use Discord\Builders\Components\TextInput;
use Discord\Builders\MessageBuilder;
use Discord\Discord;
use Discord\Helpers\Collection;
use Discord\Parts\Interactions\Interaction;

class DiscordComponent
{

    private array $modalComponents;

    private DiscordPlan $plan;

    public function __construct(DiscordPlan $plan)
    {
        $this->plan = $plan;
    }

    public function getModalComponent(Discord $discord, Interaction $interaction, string|object $key): ?object
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

    public function showModal(Discord $discord, Interaction $interaction, string|object $key): void
    {
        $modal = is_object($key) ? $key : $this->getModalComponent($discord, $interaction, $key);

        if ($modal !== null) {
            $interaction->showModal(
                $modal->title,
                $modal->custom_id,
                $modal->subComponents,
                function (Interaction $interaction, Collection $components) use ($discord, $modal) {
                    if ($modal->response !== null) {
                        $interaction->acknowledgeWithResponse(true);
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
                        array($components)
                    );
                }
            );
        }
    }
}