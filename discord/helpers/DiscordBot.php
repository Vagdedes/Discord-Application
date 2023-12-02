<?php

/*
 * Bot Features:
 * Custom Commands
 * Channel Logs
 * Invite Tracker
 * Social Alerts
 * -
 * AI Text Messages
 * AI Image Messages
 * Persistent Messages
 * Reminder Messages
 * Welcome & Goodbye Messages
 * -
 * Counting Channels
 * Statistics Channels
 * Temporary Channels
 * Anti-Expiration Threads
 * -
 * Reaction Polls
 * Reaction Roles
 * -
 * User Targets
 * User Tickets
 * User Levels
 * User Notes
 * User Questionnaire
 */

use Discord\Discord;

class DiscordBot
{
    public int $botID;
    public array $plans;
    private string $refreshDate;
    public Discord $discord;
    public DiscordUtilities $utilities;
    private mixed $account;
    private int $counter;

    public function __construct(Discord $discord, int|string $botID)
    {
        $this->counter = 0;
        $this->discord = $discord;
        $this->botID = $botID;
        $this->plans = array();
        $this->utilities = new DiscordUtilities($this->discord);

        $this->load();
        $this->refreshDate = get_future_date(DiscordProperties::SYSTEM_REFRESH_TIME);
    }

    private function load(): void
    {
        $query = get_sql_query(
            BotDatabaseTable::BOT_PLANS,
            array("id", "account_id"),
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
            global $logger;
            $logger->logError(null, "(1) Found no plans for bot with ID: " . $this->botID);
            // In case connection or database fails, log but do not exit
        } else {
            $permission = "patreon.subscriber.discord.bot";
            $account = new Account();

            foreach ($query as $arrayKey => $plan) {
                if ($plan->account_id !== null) {
                    $account = $account->getNew($plan->account_id);

                    if (!$account->exists() || !$account->getPermissions()->hasPermission($permission)) {
                        unset($query[$arrayKey]);
                        continue;
                    }
                    $this->account = $account;
                }
                $this->plans[] = new DiscordPlan(
                    $this->discord,
                    $this,
                    $this->botID,
                    $plan->id
                );
            }

            if (empty($query)) {
                global $logger;
                $logger->logError(null, "(2) Found no plans for bot with ID: " . $this->botID);
            }
        }
    }

    public function refresh(): void
    {
        if (get_current_date() > $this->refreshDate) {
            $this->refreshDate = get_future_date(DiscordProperties::SYSTEM_REFRESH_TIME);
            $this->counter++;
            reset_all_sql_connections();
            clear_memory(null);

            if ($this->counter % 10 === 0) {
                $this->discord->close(true);
                initiate_discord_bot();
            } else {
                $this->plans = array();
                $this->load();
            }
        }
    }

    public function getAccount(): mixed
    {
        return $this->account;
    }
}