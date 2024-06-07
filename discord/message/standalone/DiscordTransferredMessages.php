<?php

use Discord\Builders\MessageBuilder;
use Discord\Parts\Channel\Message;
use Discord\Parts\Embed\Embed;
use Discord\Parts\Thread\Thread;

class DiscordTransferredMessages
{
    private DiscordBot $bot;
    private array $sources;

    public function __construct(DiscordBot $bot)
    {
        $this->bot = $bot;
        $this->sources = get_sql_query(
            BotDatabaseTable::BOT_MESSAGE_TRANSFERRER,
            null,
            array_merge(array(
                array("deletion_date", null),
                    null,
                    array("expiration_date", "IS", null, 0),
                    array("expiration_date", ">", get_current_date()),
                    null
                ), $this->bot->utilities->getServersQuery())
        );

        if (!empty($this->sources)) {
            $channels = array();

            foreach ($this->sources as $arrayKey => $channel) {
                $channel->channels = get_sql_query(
                    BotDatabaseTable::BOT_MESSAGE_TRANSFERRER_CHANNELS,
                    null,
                    array(
                        array("deletion_date", null),
                        array("message_transferrer_id", $channel->id),
                        null,
                        array("expiration_date", "IS", null, 0),
                        array("expiration_date", ">", get_current_date()),
                        null
                    )
                );
                if (!empty($channel->channels)) {
                    foreach ($channel->channels as $childKey => $sentChannel) {
                        unset($channel->channels[$childKey]);
                        $channel->channels[$this->bot->utilities->hash(
                            $sentChannel->server_id,
                            $sentChannel->channel_id,
                            $sentChannel->thread_id
                        )] = $sentChannel;
                    }
                    $channels[$channel->id] = $channel;
                }
            }
            $this->sources = $channels;
        }
    }

    public function trackCreation(Message $message): void
    {
        if (!empty($this->sources)) {
            $original = $this->bot->utilities->getChannel($message->channel);

            foreach ($this->sources as $receiveChannel) {
                if ($receiveChannel->server_id == $original->guild_id
                    && ($receiveChannel->channel_id === null || $receiveChannel->channel_id == $original->id)
                    && ($receiveChannel->thread_id === null || $receiveChannel->thread_id == $message->thread?->id)) {
                    foreach ($receiveChannel->channels as $sentChannel) {
                        $channelObj = $this->bot->discord->getChannel($sentChannel->channel_id);

                        if ($channelObj !== null) {
                            if ($sentChannel->thread_id !== null) {
                                $found = false;

                                if (!empty($channelObj->threads->first())) {
                                    foreach ($channelObj->threads as $thread) {
                                        if ($thread instanceof Thread && $sentChannel->thread_id == $thread->id) {
                                            $found = true;
                                            $channelObj = $thread;
                                            break;
                                        }
                                    }
                                }

                                if (!$found) {
                                    global $logger;
                                    $logger->logError(
                                        null,
                                        "Failed to find message-transferrer thread for creation with ID: " . $sentChannel->thread_id
                                    );
                                    continue;
                                }
                            }
                            $channelObj->sendMessage($this->buildMessage($message))->done(function (Message $endMessage)
                            use ($message, $receiveChannel, $channelObj) {
                                $startChannel = $message->channel;

                                if (!sql_insert(
                                    BotDatabaseTable::BOT_MESSAGE_TRANSFERRER_TRACKING,
                                    array(
                                        "message_transferrer_id" => $receiveChannel->id,
                                        "message_content" => $message->content,
                                        "start_server_id" => $message->guild_id,
                                        "start_channel_id" => $startChannel instanceof Thread ? $startChannel->parent_id : $startChannel->id,
                                        "start_thread_id" => $startChannel instanceof Thread ? $startChannel->id : null,
                                        "start_message_id" => $message->id,
                                        "start_user_id" => $message->author->id,
                                        "end_server_id" => $channelObj->guild_id,
                                        "end_channel_id" => $channelObj instanceof Thread ? $channelObj->parent_id : $channelObj->id,
                                        "end_thread_id" => $channelObj instanceof Thread ? $channelObj->id : null,
                                        "end_message_id" => $endMessage->id,
                                        "end_user_id" => $endMessage->author->id,
                                        "creation_date" => $message->timestamp
                                    )
                                )) {
                                    global $logger;
                                    $logger->logError(
                                        null,
                                        "Failed to insert message-transferrer message-creation with ID: " . $receiveChannel->id
                                    );
                                }
                            });
                        }
                    }
                    break;
                }
            }
        }
    }

    public function trackModification(Message $editedMessage): void
    {
        $messages = $this->getMessages($editedMessage);

        if (!empty($messages)) {
            foreach ($messages as $sentMessage) {
                $object = $this->sources[$sentMessage->message_transferrer_id] ?? null;

                if ($object !== null) {
                    $channel = $object->channels[$this->bot->utilities->hash(
                        $sentMessage->end_server_id,
                        $sentMessage->end_channel_id,
                        $sentMessage->end_thread_id
                    )] ?? null;

                    if ($channel !== null
                        && $channel->track_modification !== null) {
                        $channel = $this->bot->discord->getChannel($sentMessage->end_channel_id);

                        if ($channel !== null
                            && $channel->allowText()
                            && $channel->guild_id == $sentMessage->end_server_id) {
                            if ($sentMessage->end_thread_id !== null) {
                                $found = false;

                                if (!empty($channel->threads->first())) {
                                    foreach ($channel->threads as $thread) {
                                        if ($thread instanceof Thread && $sentMessage->end_thread_id == $thread->id) {
                                            $found = true;
                                            $channel = $thread;
                                            break;
                                        }
                                    }
                                }

                                if (!$found) {
                                    global $logger;
                                    $logger->logError(
                                        null,
                                        "Failed to find message-transferrer thread for modification with ID: " . $sentMessage->end_thread_id
                                    );
                                    continue;
                                }
                            }
                            $channel->messages->fetch($sentMessage->end_message_id)->done(function (Message $message)
                            use ($sentMessage, $editedMessage) {
                                if (set_sql_query(
                                    BotDatabaseTable::BOT_MESSAGE_TRANSFERRER_TRACKING,
                                    array(
                                        "message_content" => $editedMessage->content,
                                        "edited" => true
                                    ),
                                    array(
                                        array("id", $sentMessage->id)
                                    ),
                                    null,
                                    1
                                )) {
                                    $message->edit($this->buildMessage($editedMessage));
                                } else {
                                    global $logger;
                                    $logger->logError(
                                        null,
                                        "Failed to update message-transferrer message-modification with ID: " . $sentMessage->id
                                    );
                                }
                            });
                        }
                    }
                }
            }
        }
    }

    public function trackDeletion(object $message): void
    {
        $messages = $this->getMessages($message);

        if (!empty($messages)) {
            $date = get_current_date();

            foreach ($messages as $sentMessage) {
                $object = $this->sources[$sentMessage->message_transferrer_id] ?? null;

                if ($object !== null) {
                    $channel = $object->channels[$this->bot->utilities->hash(
                        $sentMessage->end_server_id,
                        $sentMessage->end_channel_id,
                        $sentMessage->end_thread_id
                    )] ?? null;

                    if ($channel !== null
                        && $channel->track_deletion !== null) {
                        $channel = $this->bot->discord->getChannel($sentMessage->end_channel_id);

                        if ($channel !== null
                            && $channel->allowText()
                            && $channel->guild_id == $sentMessage->end_server_id) {
                            if ($sentMessage->end_thread_id !== null) {
                                $found = false;

                                if (!empty($channel->threads->first())) {
                                    foreach ($channel->threads as $thread) {
                                        if ($thread instanceof Thread && $sentMessage->end_thread_id == $thread->id) {
                                            $found = true;
                                            $channel = $thread;
                                            break;
                                        }
                                    }
                                }

                                if (!$found) {
                                    global $logger;
                                    $logger->logError(
                                        null,
                                        "Failed to find message-transferrer thread for deletion with ID: " . $sentMessage->end_thread_id
                                    );
                                    continue;
                                }
                            }
                            $channel->messages->fetch($sentMessage->end_message_id)->done(function (Message $message)
                            use ($sentMessage, $date) {
                                if (set_sql_query(
                                    BotDatabaseTable::BOT_MESSAGE_TRANSFERRER_TRACKING,
                                    array(
                                        "deletion_date" => $date
                                    ),
                                    array(
                                        array("id", $sentMessage->id)
                                    ),
                                    null,
                                    1
                                )) {
                                    $message->delete();
                                } else {
                                    global $logger;
                                    $logger->logError(
                                        null,
                                        "Failed to update message-transferrer message-deletion with ID: " . $sentMessage->id
                                    );
                                }
                            });
                        }
                    }
                }
            }
        }
    }

    private function getMessages(object $message): array
    {
        return get_sql_query(
            BotDatabaseTable::BOT_MESSAGE_TRANSFERRER_TRACKING,
            null,
            array(
                array("deletion_date", null),
                array("start_server_id", $message->guild_id),
                array("start_channel_id", $message->channel_id),
                array("start_message_id", $message->id)
            )
        );
    }

    private function buildMessage(Message $message): MessageBuilder
    {
        $messageBuilder = MessageBuilder::new();
        $embed = new Embed($this->bot->discord);
        $embed->setAuthor($message->author->username, $message->author->avatar);
        $embed->setDescription(
            DiscordSyntax::HEAVY_CODE_BLOCK . $message->content . DiscordSyntax::HEAVY_CODE_BLOCK
        );
        $embed->setTitle($message->channel->name);
        $inviteURL = DiscordInviteTracker::getInvite($message->guild)?->invite_url;

        if ($inviteURL !== null) {
            $embed->setURL($inviteURL);
        }
        $messageBuilder->addEmbed($embed);
        return $messageBuilder;
    }
}