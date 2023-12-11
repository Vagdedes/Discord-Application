<?php

use Discord\Builders\MessageBuilder;
use Discord\Parts\Channel\Channel;
use Discord\Parts\Channel\Message;
use Discord\Parts\Thread\Thread;

class DiscordAntiExpirationThreads
{
    private DiscordPlan $plan;

    public function __construct(DiscordPlan $plan)
    {
        $this->plan = $plan;
        $query = get_sql_query(
            BotDatabaseTable::BOT_MESSAGE_REFRESH,
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

        if (!empty($query)) {
            foreach ($query as $row) {
                $channel = $this->plan->bot->discord->getChannel($row->channel_id);

                if ($channel !== null
                    && $channel->guild_id == $row->server_id) {
                    if ($row->thread_id === null) {
                        if ($channel->allowText()) {
                            $this->execute($channel, $row);
                        }
                    } else {
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