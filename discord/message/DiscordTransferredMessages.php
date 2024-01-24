<?php

use Discord\Parts\Channel\Message;

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
                $this->channels[$arrayKey] = $channel;
            }
        }
    }

    public function trackCreation(Message $message): void
    {
    }

    public function trackModification(Message $message): void
    {

    }

    public function trackDeletion(object $message): void
    {

    }
}