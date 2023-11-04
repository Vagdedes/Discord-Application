<?php

use Discord\Builders\Components\ActionRow;
use Discord\Builders\Components\Button;
use Discord\Builders\Components\Option;
use Discord\Builders\Components\StringSelect;
use Discord\Builders\MessageBuilder;
use Discord\Helpers\Collection;
use Discord\Parts\Channel\Channel;
use Discord\Parts\Channel\Message;
use Discord\Parts\Embed\Embed;
use Discord\Parts\Interactions\Interaction;

class DiscordControlledMessages
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
                         string|object $key, bool $ephemeral,
                         bool          $modal = false): bool
    {
        $message = $this->messages[$key] ?? null;

        if ($message !== null) {
            $this->plan->conversation->acknowledgeMessage(
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

        $this->plan->conversation->acknowledgeMessage($interaction, $messageBuilder, $ephemeral);
        return true;
    }

    private function build(?Interaction $interaction, object $messageRow): MessageBuilder
    {
        $messageBuilder = MessageBuilder::new()->setContent(
            empty($messageRow->message_content) ? ""
                : $messageRow->message_content
        );
        $embed = new Embed($this->plan->discord);
        $addEmbed = false;

        if (!empty($messageRow->embed_title)) {
            $embed->setTitle($messageRow->embed_title);
            $addEmbed = true;
        }
        if (!empty($messageRow->embed_description)) {
            $embed->setDescription($messageRow->embed_description);
            $addEmbed = true;
        }
        if (!empty($messageRow->embed_url)) {
            $embed->setUrl($messageRow->embed_url);
            $addEmbed = true;
        }
        if ($messageRow->embed_color !== null) {
            $embed->setColor($messageRow->embed_color);
            $addEmbed = true;
        }
        if ($messageRow->embed_image !== null) {
            $embed->setImage($messageRow->embed_image);
            $addEmbed = true;
        }
        if ($messageRow->embed_timestamp !== null) {
            $embed->setTimestamp(strtotime($messageRow->embed_timestamp));
            $addEmbed = true;
        }
        if ($messageRow->embed_footer !== null) {
            $embed->setFooter($messageRow->embed_footer);
            $addEmbed = true;
        }
        if (!empty($messageRow->embed_author_name)) {
            $embed->setAuthor(
                $messageRow->embed_author_name,
                $messageRow->embed_author_icon_url,
                $messageRow->embed_author_url,
            );
            $addEmbed = true;
        }
        if ($addEmbed) {
            $messageBuilder->addEmbed($embed);
        }
        $messageBuilder = $this->plan->component->addButtons(
            $interaction,
            $messageBuilder,
            $messageRow->id
        );
        $messageBuilder = $this->plan->component->addSelection(
            $interaction,
            $messageBuilder,
            $messageRow->id
        );
        return $this->plan->listener->callMessageBuilderCreation(
            $interaction,
            $messageBuilder,
            $messageRow->listener_class,
            $messageRow->listener_method
        );
    }

    private function process(array $array, int $position): void
    {
        $messageRow = $array[$position] ?? null;

        if ($messageRow !== null) {
            if ($messageRow->server_id !== null
                && $messageRow->channel_id !== null) {
                global $logger;
                $channel = $this->plan->discord->getChannel($messageRow->channel_id);

                if ($channel !== null
                    && $channel->guild_id == $messageRow->server_id) {
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
                        $this->newMessage($channel, $messageRow, $oldMessageRow, $array, $position);
                    } else {
                        $this->editMessage($channel, $custom, $messageRow, $oldMessageRow, $array, $position);
                    }
                }
            } else if ($messageRow->name !== null) {
                $this->messages[$messageRow->name] = $messageRow;
                $this->process($array, $position + 1);
            }
        }
    }

    private function newMessage(Channel $channel,
                                object  $messageRow, object $oldMessageRow,
                                array   $array, int $position): void
    {
        $channel->sendMessage($this->build(null, $messageRow))->done(
            function (Message $message) use ($messageRow, $oldMessageRow, $array, $position) {
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

    private function editMessage(Channel $channel,
                                 bool    $custom,
                                 object  $messageRow, object $oldMessageRow,
                                 array   $array, int $position): void
    {
        $channel->getMessageHistory(array("limit" => 10))->done(
            function (Collection $messages) use ($custom, $messageRow, $oldMessageRow, $array, $position) {
                foreach ($messages as $message) {
                    if ($message instanceof Message
                        && $message->user_id == $this->plan->botID
                        && $message->id == $oldMessageRow->message_id) {
                        if ($custom) {
                            $messageRow->message_id = $message->id;
                        }
                        $message->edit($this->build(null, $messageRow));
                        break;
                    }
                }
                $this->process($array, $position + 1);
            }
        );
    }
}