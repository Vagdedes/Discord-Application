<?php

use Discord\Discord;

class DiscordBot
{
    public int $botID;
    public array $plans;
    private string $refreshDate;
    private Discord $discord;

    public function __construct(Discord $discord, int|string $botID)
    {
        $this->discord = $discord;
        $this->botID = $botID;
        $this->plans = array();
        $this->refreshDate = get_past_date("2 seconds");
        $this->refresh();
    }

    private function refresh(): void
    {
        $date = get_current_date();

        if ($date > $this->refreshDate) {
            $this->refreshDate = get_future_date((DiscordProperties::SYSTEM_REFRESH_MILLISECONDS / 60_000) . " minutes");
            $query = get_sql_query(
                BotDatabaseTable::BOT_PLANS,
                array("id"),
                array(
                    array("bot_id", $this->botID),
                    array("deletion_date", null),
                    null,
                    array("expiration_date", "IS", null, 0),
                    array("expiration_date", ">", $date),
                    null
                )
            );

            if (empty($query)) {
                global $logger;
                $logger->logError(null, "Found no plans for bot with ID: " . $this->botID);
                // In case connection or database fails, log but do not exit
            } else {
                foreach ($query as $plan) {
                    $this->plans[] = new DiscordPlan($this->discord, $this->botID, $plan->id);
                }
            }
        }
    }

    public function getPlans(): array
    {
        $this->refresh();
        return $this->plans;
    }
}