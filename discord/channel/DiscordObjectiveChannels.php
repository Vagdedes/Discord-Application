<?php

use Discord\Builders\MessageBuilder;
use Discord\Parts\Channel\Message;
use Discord\Parts\Embed\Embed;

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
    }

    //todo creation channel
    //todo note channel on deletion (embed messages) [listener]

    public function trackCreation(Message $message): void
    {
        if (!empty($this->channels)) {
            foreach ($this->channels as $channel) {
                if ($channel->start_server_id === $message->guild_id
                    && $channel->start_channel_id == $message->channel_id
                    && ($channel->thread_id === null || $channel->thread_id == $message->thread->id)) {
                    if (sql_insert(
                        BotDatabaseTable::BOT_OBJECTIVE_CHANNEL_TRACKING,
                        array(
                            "plan_id" => $this->plan->planID,
                            "start_server_id" => $channel->start_server_id,
                            "start_channel_id" => $channel->start_channel_id,
                            "start_thread_id" => $channel->thread_id,
                            "start_message_id" => $message->id,
                            "creation_date" => get_current_date()
                        ))) {
                        $this->messages[$this->plan->utilities->hash($message->guild_id, $message->channel_id, $message->id)] = $message->author->avatar;
                    } else {
                        global $logger;
                        $logger->logError($this->plan->planID, "Failed to insert objective-channel message-creation with ID: " . $channel->id);
                    }
                    break;
                }
            }
        }
    }

    public function trackDeletion(object $message): void
    {
        if (array_key_exists(
            $this->plan->utilities->hash($message->guild_id, $message->channel_id, $message->id),
            $this->messages
        )) {
            $messageBuilder = MessageBuilder::new();
            $embed = new Embed($this->plan->bot->discord);
            $embed->setAuthor($message->author->username, $message->author->avatar);
            $messageBuilder->addEmbed($embed);

            $message->reply($messageBuilder)->done(function (Message $endMessage) use ($message) {
                if (!set_sql_query(
                    BotDatabaseTable::BOT_OBJECTIVE_CHANNEL_TRACKING,
                    array(
                        "end_message_id" => $endMessage->id,
                        ""
                    ),
                    array(
                        array("start_server_id", $message->guild_id),
                        array("start_channel_id", $message->channel_id),
                        array("start_message_id", $message->id)
                    ),
                    null,
                    1
                )) {
                    global $logger;
                    $logger->logError($this->plan->planID, "Failed to update objective-channel message-deletion with ID: " . $message->id);
                }
            });
        }
    }

}