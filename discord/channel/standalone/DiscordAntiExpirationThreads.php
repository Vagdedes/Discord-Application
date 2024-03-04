<?php

use Discord\Builders\MessageBuilder;
use Discord\Parts\Channel\Channel;
use Discord\Parts\Channel\Message;
use Discord\Parts\Thread\Thread;

class DiscordAntiExpirationThreads
{
    private DiscordBot $bot;

    public function __construct(DiscordBot $bot)
    {
        $this->bot = $bot;
        $query = get_sql_query(
            BotDatabaseTable::BOT_MESSAGE_REFRESH,
            null,
            array_merge(array(
                array("deletion_date", null),
                null,
                array("expiration_date", "IS", null, 0),
                array("expiration_date", ">", get_current_date()),
                null
            ), $this->bot->utilities->getServersQuery())
        );

        if (!empty($query)) {
            foreach ($query as $row) {
                $channel = $this->bot->discord->getChannel($row->channel_id);

                if ($channel !== null
                    && $channel->guild_id == $row->server_id
                    && $channel->allowText()) {
                    if ($row->thread_id === null) {
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

    private function execute(Channel|Thread $channel, object $row): void
    {
        $channel->sendMessage(MessageBuilder::new()->setContent($row->message_content))->done(
            function (Message $message) use ($row) {
                if ($row->milliseconds_retention === null) {
                    $message->delete();
                } else {
                    $message->delayedDelete($row->milliseconds_retention);
                }
            }
        );
    }

}