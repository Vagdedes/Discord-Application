<?php

use Discord\Builders\MessageBuilder;
use Discord\Parts\Channel\Channel;
use Discord\Parts\Channel\Message;
use Discord\Parts\Thread\Thread;

class DiscordChannelNotifications
{
    private DiscordPlan $plan;
    private array $notifications;

    public function __construct(DiscordPlan $plan)
    {
        $this->plan = $plan;
        $this->notifications = get_sql_query(
            BotDatabaseTable::BOT_CHANNEL_NOTIFICATIONS,
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

    private function execute(Channel|Thread $channel, object $row): void
    {

    }

}