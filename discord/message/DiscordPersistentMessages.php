<?php

use Discord\Builders\Components\ActionRow;
use Discord\Builders\Components\Button;
use Discord\Builders\Components\Option;
use Discord\Builders\Components\StringSelect;
use Discord\Builders\MessageBuilder;
use Discord\Helpers\Collection;
use Discord\Parts\Channel\Channel;
use Discord\Parts\Channel\Message;
use Discord\Parts\Interactions\Interaction;
use Discord\Parts\Thread\Thread;

class DiscordPersistentMessages
{
    private DiscordBot $bot;
    private array $messages;

    public function __construct(DiscordBot $bot)
    {
        $this->bot = $bot;
        $query = get_sql_query(
            BotDatabaseTable::BOT_CONTROLLED_MESSAGES,
            null,
            array(
                array("deletion_date", null),
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
            $this->processDatabase($query, 0);
            $this->processDiscord();
        }
    }

    public function send(Interaction   $interaction,
                         string|object $key, bool $ephemeral): bool
    {
        $message = $this->messages[$key] ?? null;

        if ($message !== null) {
            $interaction->acknowledge()->done(function () use ($interaction, $ephemeral, $message) {
                $interaction->sendFollowUpMessage(
                    $this->build($interaction, $message),
                    $ephemeral
                );
            });
            return true;
        } else {
            return false;
        }
    }

    public function get(?object $interaction, string|object $key): ?MessageBuilder
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
        $object = $this->bot->instructions->getObject(
            $interaction->guild,
            $interaction->channel,
            $interaction->user,
            $interaction->message,
        );
        $messageBuilder = MessageBuilder::new()->setContent(
            $this->bot->instructions->replace(array($message), $object)[0]
        );

        foreach ($components as $component) {
            if ($component instanceof StringSelect) {
                $placeholder = $component->getPlaceholder();

                if ($placeholder !== null) {
                    $component->setPlaceholder(
                        $this->bot->instructions->replace(array($placeholder), $object)[0]
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
                                $this->bot->instructions->replace(array($description), $object)[0]
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
                                $this->bot->instructions->replace(array($label), $object)[0]
                            );
                        }
                    }
                }
            }
            $messageBuilder->addComponent($component);
        }

        $this->bot->utilities->acknowledgeMessage($interaction, $messageBuilder, $ephemeral);
        return true;
    }

    private function build(?object $interaction, object $messageRow): MessageBuilder
    {
        $messageBuilder = $this->bot->utilities->buildMessageFromObject(
            $messageRow,
            $interaction instanceof Interaction ?
                $this->bot->instructions->getObject(
                    $interaction->guild,
                    $interaction->channel,
                    $interaction->member,
                    $interaction->message
                ) : null
        );

        if ($messageBuilder === null) {
            $messageBuilder = MessageBuilder::new();
        }
        $messageBuilder = $this->bot->component->addButtons(
            $interaction instanceof Interaction ? $interaction : null,
            $messageBuilder,
            $messageRow->id,
            $messageRow->listener_recursion !== null
        );
        $messageBuilder = $this->bot->component->addSelection(
            $interaction instanceof Interaction ? $interaction : null,
            $messageBuilder,
            $messageRow->id,
            $messageRow->listener_recursion !== null
        );
        $messageBuilder = $this->bot->listener->callMessageBuilderCreation(
            $interaction instanceof Interaction ? $interaction : null,
            $messageBuilder,
            $messageRow->listener_class,
            $messageRow->listener_method
        );
        return $this->bot->interactionRoles->process($messageBuilder, $messageRow->id);
    }

    private function processDiscord(): void
    {
        $discord = $this->bot->discord;

        if (!empty($discord->guilds->first())) {
            foreach ($discord->guilds as $guild) {
                if (empty($guild->channels->first())) {
                    continue;
                }
                foreach ($guild->channels as $channel) {
                    $this->processDiscordChannel($channel);

                    if (!empty($channel->threads->first())) {
                        foreach ($channel->threads as $thread) {
                            $this->processDiscordChannel($thread);
                        }
                    }
                }
            }
        }
    }

    private function processDiscordChannel(Channel|Thread $channel): void
    {
        $dbMessages = $this->messages;
        $botID = $this->bot->botID;
        $bot = $this->bot;

        $channel->getMessageHistory([
            'limit' => 100,
            'cache' => true
        ])->done(function (Collection $messages) use ($dbMessages, $botID, $bot) {
            foreach ($messages as $message) {
                if ($message->user_id != $botID) {
                    continue;
                }
                foreach ($dbMessages as $dbMessage) {
                    if ($dbMessage->embed_title === null
                        && $dbMessage->embed_description === null
                        && $dbMessage->embed_image === null
                        && $dbMessage->embed_url === null
                        && $dbMessage->embed_footer === null
                        && $dbMessage->embed_author_url === null
                        && $dbMessage->embed_author_icon_url === null
                        && $dbMessage->embed_author_name === null
                        && $dbMessage->embed_timestamp === null) {
                        continue;
                    }
                    if (empty($message->embeds->first())
                        || $message->embeds->count() > 1) {
                        continue;
                    }
                    foreach ($message->embeds as $embed) {
                        if ($dbMessage->embed_title == $embed->title
                            && $dbMessage->embed_description == $embed->description
                            && $dbMessage->embed_image == $embed->image?->url
                            && $dbMessage->embed_url == $embed->url
                            && $dbMessage->embed_footer == $embed->footer?->text
                            && $dbMessage->embed_author_url == $embed->author?->url
                            && $dbMessage->embed_author_icon_url == $embed->author?->icon_url
                            && $dbMessage->embed_author_name == $embed->author?->name
                            && $dbMessage->embed_timestamp == $embed->timestamp) {
                            $message->edit($this->build(null, $dbMessage)->setContent($message->content))->done(
                                function (Message $message) use ($dbMessage, $bot) {
                                    $bot->instructions->manager->addExtra(
                                        "interactive-message-" . $message->id,
                                        $message->getRawAttributes()
                                    );
                                }
                            );
                            break 2;
                        }
                    }
                }
            }
        });
    }

    private function processDatabase(array $array, int $position): void
    {
        $messageRow = $array[$position] ?? null;

        if ($messageRow !== null) {
            if ($messageRow->server_id !== null
                && $messageRow->channel_id !== null) {
                global $logger;
                $channel = $this->bot->discord->getChannel($messageRow->channel_id);

                if ($channel !== null
                    && $channel->guild_id == $messageRow->server_id) {
                    if ($messageRow->thread_id !== null) {
                        $finalChannel = null;

                        if (!empty($channel->threads->first())) {
                            foreach ($channel->threads as $thread) {
                                if ($thread instanceof Thread && $messageRow->thread_id == $thread->id) {
                                    $finalChannel = $thread;
                                    break;
                                }
                            }
                        }
                    } else if ($this->bot->utilities->allowText($channel)) {
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
                                    "Message {$oldMessageRow->id} is a copy of {$oldMessageRow->copy_of} but it does not exist."
                                );
                                $this->processDatabase($array, $position + 1);
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
                        $this->processDatabase($array, $position + 1);
                    }
                }
            } else if ($messageRow->name !== null) {
                $this->messages[$messageRow->name] = $messageRow;
                $this->processDatabase($array, $position + 1);
            }
        }
    }

    private function newMessage(Thread|Channel $channel,
                                object         $messageRow, object $oldMessageRow,
                                array          $array, int $position): void
    {
        $bot = $this->bot;
        $channel->sendMessage($this->build(null, $messageRow))->done(
            function (Message $message) use ($messageRow, $oldMessageRow, $array, $position, $bot) {
                $this->bot->component->addReactions($message, $messageRow->id);
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
                $bot->instructions->manager->addExtra(
                    "interactive-message-" . $message->id,
                    $message->getRawAttributes()
                );
                $this->processDatabase($array, $position + 1);
            }
        );
    }

    private function editMessage(Thread|Channel $channel,
                                 bool           $custom,
                                 object         $messageRow, object $oldMessageRow,
                                 array          $array, int $position): void
    {
        try {
            $bot = $this->bot;
            $channel->messages->fetch($oldMessageRow->message_id, true)->done(
                function (Message $message) use ($channel, $custom, $messageRow, $oldMessageRow, $array, $position, $bot) {
                    if ($message->user_id == $this->bot->botID) {
                        if ($custom) {
                            $messageRow->message_id = $message->id;
                        }
                        $message->edit($this->build(null, $messageRow))->done(
                            function (Message $message) use ($bot) {
                                $bot->instructions->manager->addExtra(
                                    "interactive-message-" . $message->id,
                                    $message->getRawAttributes()
                                );
                            }
                        );
                    } else {
                        $message->delete();
                        $this->newMessage($channel, $messageRow, $oldMessageRow, $array, $position);
                    }
                    $this->processDatabase($array, $position + 1);
                }
            );
        } catch (Throwable $ignored) {
            $this->processDatabase($array, $position + 1);
        }
    }

}