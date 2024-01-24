<?php

use Discord\Builders\MessageBuilder;
use Discord\Parts\Channel\Channel;
use Discord\Parts\Channel\Message;
use Discord\Parts\Embed\Embed;
use Discord\Parts\Thread\Thread;

class DiscordObjectiveChannels
{
    private DiscordPlan $plan;
    private array $channels, $messages;

    public function __construct(DiscordPlan $plan)
    {
        $this->plan = $plan;
        $this->messages = array();
        $this->channels = get_sql_query(
            BotDatabaseTable::BOT_OBJECTIVE_CHANNELS,
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
                $channel->roles = get_sql_query(
                    BotDatabaseTable::BOT_OBJECTIVE_CHANNEL_ROLES,
                    null,
                    array(
                        array("objective_channel_id", $channel->id),
                        array("deletion_date", null),
                        null,
                        array("expiration_date", "IS", null, 0),
                        array("expiration_date", ">", get_current_date()),
                        null
                    )
                );
                $this->channels[$arrayKey] = $channel;
                $this->getChannel($channel, true);
                $this->getChannel($channel, false);
            }
        }
    }

    public function trackCreation(Message $message): bool
    {
        if (!empty($this->channels)) {
            foreach ($this->channels as $channel) {
                if ($channel->start_server_id === $message->guild_id
                    && $channel->start_channel_id == $message->channel_id
                    && ($channel->thread_id === null || $channel->thread_id == $message->thread->id)) {
                    $channelObj = $this->plan->bot->utilities->getChannel($message->channel);

                    if (sql_insert(
                        BotDatabaseTable::BOT_OBJECTIVE_CHANNEL_TRACKING,
                        array(
                            "plan_id" => $this->plan->planID,
                            "start_server_id" => $message->guild_id,
                            "start_category_id" => $channelObj->parent_id,
                            "start_channel_id" => $channelObj->id,
                            "start_thread_id" => $message->thread?->id,
                            "start_message_id" => $message->id,
                            "start_user_id" => $message->user_id,
                            "creation_date" => get_current_date()
                        ))) {
                        // Only these because more cannot be held by just a deleted message object
                        $this->messages[$this->plan->utilities->hash(
                            $message->guild_id,
                            $message->channel_id,
                            $message->id)] = array($channel, $message);
                    } else {
                        global $logger;
                        $logger->logError($this->plan->planID, "Failed to insert objective-channel message-creation with ID: " . $channel->id);
                    }
                    return true;
                }
            }
        }
        return false;
    }

    public function trackDeletion(object $message): void
    {
        $hash = $this->plan->utilities->hash(
            $message->guild_id,
            $message->channel_id,
            $message->id
        );

        if (array_key_exists(
            $hash,
            $this->messages
        )) {
            $data = $this->messages[$hash];
            $channel = $this->getChannel($data[0], false);

            if ($channel !== null) {
                $message = $data[1];
                $messageBuilder = MessageBuilder::new();
                $embed = new Embed($this->plan->bot->discord);
                $embed->setAuthor($message->author->username, $message->author->avatar);
                $embed->setDescription($message->content);
                $messageBuilder->addEmbed($embed);

                $channel->sendMessage($messageBuilder)->done(function (Message $endMessage) use ($message) {
                    $channelObj = $this->plan->bot->utilities->getChannel($endMessage->channel);

                    if (!set_sql_query(
                        BotDatabaseTable::BOT_OBJECTIVE_CHANNEL_TRACKING,
                        array(
                            "end_server_id" => $endMessage->guild_id,
                            "end_category_id" => $channelObj->parent_id,
                            "end_channel_id" => $channelObj->id,
                            "end_thread_id" => $endMessage->thread?->id,
                            "end_message_id" => $endMessage->id,
                            "end_user_id" => $endMessage->user_id,
                            "transfer_date" => get_current_date()
                        ),
                        array(
                            array("start_server_id", $message->guild_id),
                            array("start_category_id", $message->channel->parent_id),
                            array("start_channel_id", $message->channel_id),
                            array("start_thread_id", $message->channel_id),
                            array("start_message_id", $message->id),
                            array("start_user_id", $message->user_id)
                        ),
                        null,
                        1
                    )) {
                        global $logger;
                        $logger->logError($this->plan->planID, "Failed to update objective-channel message-deletion with ID: " . $message->id);
                    }
                });
            } else {
                global $logger;
                $logger->logError(
                    $this->plan->planID,
                    "Failed to get channel for objective-channel message-deletion with ID: " . $message->id
                );
            }
        }
    }

    private function createChannel(object $channel): ?Channel
    {
        return null;
    }

    private function createThread(object $channel): ?Thread
    {
        return null;
    }

    private function getChannel(object $channel, bool $creation): Channel|Thread|null
    {
        if ($creation ? ($channel->start_channel_id !== null) : ($channel->end_channel_id !== null)) {
            $channelObj = $this->plan->bot->discord->getChannel(
                $creation
                    ? $channel->start_channel_id
                    : $channel->end_channel_id
            );

            if ($channelObj !== null) {
                if ($creation ? ($channel->start_thread_id !== null) : ($channel->end_thread_id !== null)) {
                    if (!empty($channelObj->threads->first())) {
                        foreach ($channelObj->threads as $thread) {
                            if ($thread instanceof Thread
                                && ($creation
                                    ? $channel->start_thread_id
                                    : $channel->end_thread_id) == $thread->id) {
                                return $thread;
                            }
                        }
                    }
                    return $this->createThread($channel);
                } else {
                    return $channelObj;
                }
            } else {
                return $this->createChannel($channel);
            }
        } else {
            return $this->createChannel($channel);
        }
    }
}