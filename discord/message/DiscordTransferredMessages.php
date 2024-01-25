<?php

use Discord\Builders\MessageBuilder;
use Discord\Parts\Channel\Message;
use Discord\Parts\Embed\Embed;

class DiscordTransferredMessages
{
    private DiscordPlan $plan;
    private array $channels;

    public function __construct(DiscordPlan $plan)
    {
        $this->plan = $plan;
        $this->channels = get_sql_query(
            BotDatabaseTable::BOT_MESSAGE_TRANSFERRER,
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

        if (!empty($this->channels)) {
            foreach ($this->channels as $arrayKey => $channel) {
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
                unset($this->channels[$arrayKey]);

                if (!empty($channel->channels)) {
                    foreach ($channel->channels as $childKey => $sentChannel) {
                        unset($channel->channels[$childKey]);
                        $channel->channels[$this->plan->utilities->hash(
                            $sentChannel->server_id,
                            $sentChannel->channel_id,
                            $sentChannel->thread_id
                        )] = $sentChannel;
                    }
                    $this->channels[$channel->id] = $channel;
                }
            }
        }
    }

    public function trackCreation(Message $message): void
    {
        if (!empty($this->channels)) {
            $original = $this->plan->utilities->getChannel($message->channel);

            foreach ($this->channels as $receiveChannel) {
                if ($receiveChannel->server_id === $original->guild_id
                    && ($receiveChannel->channel_id === null || $receiveChannel->channel_id === $original->id)
                    && ($receiveChannel->thread_id === null || $receiveChannel->thread_id === $message->thread?->id)) {
                    foreach ($receiveChannel->channels as $sentChannel) {
                        $channelObj = $this->plan->bot->discord->getChannel($sentChannel->channel_id);

                        if ($channelObj !== null) {
                            $channelObj->sendMessage($this->buildMessage($message))->done(function (Message $endMessage)
                            use ($message, $receiveChannel) {
                                if (!sql_insert(
                                    BotDatabaseTable::BOT_MESSAGE_TRANSFERRER_TRACKING,
                                    array(
                                        "message_transferrer_id" => $receiveChannel->id,
                                        "message_content" => $message->content,
                                        "start_server_id" => $message->guild_id,
                                        "start_channel_id" => $message->channel_id,
                                        "start_thread_id" => $message->thread?->id,
                                        "start_message_id" => $message->id,
                                        "start_user_id" => $message->author->id,
                                        "end_server_id" => $endMessage->guild_id,
                                        "end_channel_id" => $endMessage->channel_id,
                                        "end_thread_id" => $endMessage->thread?->id,
                                        "end_message_id" => $endMessage->id,
                                        "end_user_id" => $endMessage->author->id,
                                        "creation_date" => $message->timestamp
                                    )
                                )) {
                                    global $logger;
                                    $logger->logError(
                                        $this->plan->planID,
                                        "Failed to insert message-transferrer message-creation with ID: " . $receiveChannel->id
                                    );
                                }
                            });
                        }
                    }
                    break;
                }
                break;
            }
        }
    }

    public function trackModification(Message $editedMessage): void
    {
        $messages = $this->getMessages($editedMessage);

        if (!empty($messages)) {
            foreach ($messages as $sentMessage) {
                $object = $this->channels[$sentMessage->message_transferrer_id] ?? null;

                if ($object !== null) {
                    $channel = $object->channels[$this->plan->utilities->hash(
                        $sentMessage->end_server_id,
                        $sentMessage->end_channel_id,
                        $sentMessage->end_thread_id
                    )] ?? null;

                    if ($channel !== null
                        && $channel->track_modification !== null) {
                        $channel = $this->plan->bot->discord->getChannel($sentMessage->end_channel_id);

                        if ($channel !== null
                            && $channel->allowText()
                            && $channel->guild_id === $sentMessage->end_server_id) {
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
                                        $this->plan->planID,
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
                $object = $this->channels[$sentMessage->message_transferrer_id] ?? null;

                if ($object !== null) {
                    $channel = $object->channels[$this->plan->utilities->hash(
                        $sentMessage->end_server_id,
                        $sentMessage->end_channel_id,
                        $sentMessage->end_thread_id
                    )] ?? null;

                    if ($channel !== null
                        && $channel->track_deletion !== null) {
                        $channel = $this->plan->bot->discord->getChannel($sentMessage->end_channel_id);

                        if ($channel !== null
                            && $channel->allowText()
                            && $channel->guild_id === $sentMessage->end_server_id) {
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
                                        $this->plan->planID,
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
        $embed = new Embed($this->plan->bot->discord);
        $embed->setAuthor($message->author->username, $message->author->avatar);
        $embed->setDescription(
            DiscordSyntax::HEAVY_CODE_BLOCK . $message->content . DiscordSyntax::HEAVY_CODE_BLOCK
        );
        $messageBuilder->addEmbed($embed);
        return $messageBuilder;
    }
}