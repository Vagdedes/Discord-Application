<?php

use Discord\Builders\MessageBuilder;
use Discord\Helpers\Collection;
use Discord\Parts\Channel\Channel;
use Discord\Parts\Channel\Message;
use Discord\Parts\Thread\Thread;

class DiscordReminderMessages
{
    private DiscordBot $bot;

    public function __construct(DiscordBot $bot)
    {
        $this->bot = $bot;
        $query = get_sql_query(
            BotDatabaseTable::BOT_MESSAGE_REMINDERS,
            null,
            array(
                array("deletion_date", null),
                null,
                array("expiration_date", "IS", null, 0),
                array("expiration_date", ">", get_current_date()),
                null
            )
        );

        if (!empty($query)) {
            foreach ($query as $row) {
                if ($row->thread_id === null) {
                    $channel = $this->bot->discord->getChannel($row->channel_id);

                    if ($channel !== null
                        && $this->bot->utilities->allowText($channel)
                        && $channel->guild_id == $row->server_id) {
                        $this->execute($channel, $row);
                    } else if (!empty($channel->threads->first())) {
                        foreach ($channel->threads as $thread) {
                            if ($thread instanceof Thread && $row->thread_id == $thread->id) {
                                $this->execute($thread, $row);
                            }
                        }
                    }
                }
            }
        }
    }

    private function execute(Channel|Thread $channel, object $row, $checkPrevious = true): void
    {
        if (!$checkPrevious
            || empty(get_sql_query(
                BotDatabaseTable::BOT_MESSAGE_REMINDER_TRACKING,
                array("id"),
                array(
                    array("deletion_date", null),
                    array("reminder_id", $row->id),
                    array("creation_date", ">", get_past_date($row->cooldown)),
                ),
                null,
                1
            ))) {
            if ($checkPrevious && $row->check_previous !== null) {
                $query = get_sql_query(
                    BotDatabaseTable::BOT_MESSAGE_REMINDER_TRACKING,
                    array("message_id"),
                    array(
                        array("deletion_date", null),
                        array("reminder_id", $row->id),
                    ),
                    array(
                        "DESC",
                        "id"
                    ),
                    1
                );

                if (!empty($query)) {
                    $channel->getMessageHistory(array("limit" => (int)$row->check_previous))->done(
                        $this->bot->utilities->functionWithException(
                            function (Collection $messages) use ($channel, $row, $query) {
                                if (!empty($messages)) {
                                    $query = $query[0];

                                    foreach ($messages as $message) {
                                        if ($message->id == $query->message_id) {
                                            return;
                                        }
                                    }
                                }
                                $this->execute($channel, $row, false);
                            }
                        )
                    );
                }
            } else {
                $object = $this->bot->instructions->getObject(
                    $channel->guild,
                    $channel
                );
                $messageBuilder = $row->message_name !== null
                    ? $this->bot->persistentMessages->get($object, $row->message_name)
                    : MessageBuilder::new()->setContent(
                        $this->bot->instructions->replace(array($row->message_content), $object)[0]
                    );

                if ($messageBuilder !== null) {
                    $messageBuilder = $this->bot->listener->callReminderMessageImplementation(
                        $row->listener_class,
                        $row->listener_method,
                        $channel,
                        $messageBuilder,
                        $row
                    );

                    $channel->sendMessage($messageBuilder)->done(
                        $this->bot->utilities->functionWithException(
                            function (Message $message) use ($row) {
                                if (sql_insert(
                                    BotDatabaseTable::BOT_MESSAGE_REMINDER_TRACKING,
                                    array(
                                        "server_id" => $message->guild_id,
                                        "channel_id" => $message->channel_id,
                                        "thread_id" => $message->thread?->id,
                                        "message_id" => $message->id,
                                        "message_object" => @json_encode($message->getRawAttributes()),
                                        "creation_date" => get_current_date()
                                    ),
                                )) {
                                    if ($row->milliseconds_retention !== null) {
                                        $message->delayedDelete($row->milliseconds_retention);
                                    }
                                }
                            }
                        )
                    );
                } else {
                    global $logger;
                    $logger->logError(
                        "Incorrect reminder message with ID: " . $row->id
                    );
                }
            }
        }
    }
}