<?php

class DiscordBot
{
    public int $botID;
    public array $plans;
    private string $refreshDate;

    public function __construct($botID)
    {
        $this->botID = $botID;
        $this->plans = array();
        $this->refreshDate = get_past_date("2 seconds");
        $this->refresh();
    }

    public function refresh(): void
    {
        if (get_current_date() > $this->refreshDate) {
            $this->refreshDate = get_future_date((DiscordProperties::SYSTEM_REFRESH_MILLISECONDS / 60_000) . " minutes");

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
}