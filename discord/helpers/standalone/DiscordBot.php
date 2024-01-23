<?php

/*
 * Bot Features:
 * Custom Mute
 * Custom Logs
 * Custom Commands
 * -
 * Social Alerts
 * Reaction Roles
 * Invite Tracker
 * -
 * AI Text Messages
 * AI Image Messages
 * Persistent Messages
 * Reminder Messages
 * Welcome & Goodbye Messages
 * Filtered Messages
 * Transferred Messages
 * Notification Messages
 * -
 * Anti-Expiration Threads
 * Counting Channels
 * Statistics Channels
 * Temporary Channels
 * Objective Channels
 * -
 * User Notes
 * User Targets
 * User Giveaways
 * User Polls
 * User Tickets
 * User Levels
 * User Questionnaires
 */

use Discord\Discord;

class DiscordBot
{
    public int $botID;
    public array $plans;
    private string $refreshDate;
    public Discord $discord;
    public DiscordUtilities $utilities;
    public DiscordMute $mute;
    private mixed $account;
    private int $counter;
    private bool $administrator;

    public function __construct(Discord $discord, int|string $botID)
    {
        $this->counter = 0;
        $this->discord = $discord;
        $this->botID = $botID;
        $this->plans = array();
        $this->utilities = new DiscordUtilities($this->discord);
        $this->mute = new DiscordMute($this);

        $this->load();
        $this->refreshDate = get_future_date(DiscordProperties::SYSTEM_REFRESH_TIME);
    }

    private function load(): void
    {
        $this->account = new Account();
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
            $this->administrator = false;
            $logger->logError(null, "(1) Found no plans for bot with ID: " . $this->botID);
            // In case connection or database fails, log but do not exit
        } else {
            $permission = "patreon.subscriber.discord.bot";

            foreach ($query as $arrayKey => $plan) {
                if ($plan->account_id !== null) {
                    $account = $this->account->getNew($plan->account_id);

                    if (!$account->exists() || !$account->getPermissions()->hasPermission($permission)) {
                        unset($query[$arrayKey]);
                        continue;
                    }
                    $this->account = $account;
                }
                $this->plans[] = new DiscordPlan(
                    $this,
                    $plan->id
                );
            }

            if (empty($query)) {
                global $logger;
                $this->administrator = false;
                $logger->logError(null, "(2) Found no plans for bot with ID: " . $this->botID);
            } else if ($this->account->exists()) {
                $this->administrator = $this->account->getPermissions()->isAdministrator();
            } else {
                $this->administrator = false;
            }
        }
    }

    public function refresh($force = false): bool
    {
        if ($force || get_current_date() > $this->refreshDate) {
            $this->refreshDate = get_future_date(DiscordProperties::SYSTEM_REFRESH_TIME);
            $this->counter++;
            reset_all_sql_connections();
            clear_memory(null);

            if ($this->counter === 10) {
                $this->counter = 0;
                $this->discord->close(true);
                initiate_discord_bot();
            } else {
                $this->plans = array();
                load_sql_database();
                $this->load();
            }
            return true;
        } else {
            return false;
        }
    }

    public function getAccount(): mixed
    {
        return $this->account;
    }

    public function isAdministrator(): bool
    {
        return $this->administrator;
    }
}