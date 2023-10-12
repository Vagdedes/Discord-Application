<?php

class DiscordBot
{
    public int $botID;
    public array $plans;

    public function __construct($botID)
    {
        $query = get_sql_query(
            DatabaseVariables::BOT_PLANS_TABLE,
            array("id"),
            array(
                array("bot_id", $botID),
                array("deletion_date", null),
                null,
                array("expiration_date", "IS", null, 0),
                array("expiration_date", ">", get_current_date()),
                null
            )
        );

        if (empty($query)) {
            exit("Discord bot not found in the database");
        } else {
            $this->botID = $botID;
            $this->plans = array();

            foreach ($query as $plan) {
                $this->plans[] = new DiscordPlan($plan->id);
            }
        }
    }

    public function refreshWhitelist(): void
    {
        foreach ($this->plans as $plan) {
            $plan->refreshWhitelist();
        }
    }

    public function refreshPunishments(): void
    {
        foreach ($this->plans as $plan) {
            $plan->refreshPunishments();
        }
    }

    public function refreshInstructions(): void
    {
        foreach ($this->plans as $plan) {
            $plan->instructions->refresh();
        }
    }

}