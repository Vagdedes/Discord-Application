<?php

class DiscordBot
{
    public int $botID;
    public array $plans;

    public function __construct($botID)
    {
        $this->botID = $botID;
        $this->plans = array();
        $this->refresh();
    }

    public function refresh(): void
    {
        $query = get_sql_query(
            BotDatabaseTable::BOT_PLANS,
            array("id"),
            array(
                array("bot_id", $this->botID),
                array("deletion_date", null),
                null,
                array("expiration_date", "IS", null, 0),
                array("expiration_date", ">", get_current_date()),
                null
            )
        );

        if (empty($query)) {
            $this->plans = array();
        } else {
            foreach ($query as $plan) {
                $this->plans[] = new DiscordPlan($plan->id);
            }
        }
    }
}