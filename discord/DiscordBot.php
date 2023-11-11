<?php

/*
 * Features:
 * Chat & Image AI
 * Custom Commands
 * Ticket Management
 * Persistent Messages
 * Refresh Messages
 * Reminder Messages
 * Admin Goals
 * Counting Channels
 * Temporary Channels
 * Moderation Channels
 * Reaction Polls
 * User Levels
 * Cheaper Chat AI
 * Invite Tracker
 * Reaction Roles
 * Social Alerts
 * Welcome & Goodbye
 */

use Discord\Discord;

class DiscordBot
{
    public int $botID;
    public array $plans;
    private string $refreshDate;
    private Discord $discord;
    public int $processing;
    private $account;

    public function __construct(Discord $discord, int|string $botID)
    {
        $this->processing = 0;
        $this->discord = $discord;
        $this->botID = $botID;
        $this->plans = array();
        $this->refreshDate = get_future_date((DiscordProperties::SYSTEM_REFRESH_MILLISECONDS / 60_000) . " minutes");
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
        if (get_current_date() > $this->refreshDate
            && $this->processing === 0) {
            $this->discord->close(true);
        }
    }

    public function getAccount(): ?object
    {
        return $this->account;
    }
}