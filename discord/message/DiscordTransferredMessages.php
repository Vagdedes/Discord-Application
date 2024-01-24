<?php

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
    }
}