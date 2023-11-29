<?php

/*
 * User Features:
 * Cheaper Chat AI
 *
 * Bot Features:
 * Chat & Image AI
 * Custom Commands
 * Anti-Expiration Threads
 * Admin Targets
 * Moderation Logs
 * Invite Tracker
 * Social Alerts
 * Welcome & Goodbye
 * -
 * Persistent Messages
 * Reminder Messages
 * -
 * Counting Channels
 * Statistics Channels
 * Temporary Channels
 * -
 * Reaction Polls
 * Reaction Roles
 * -
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
    private Discord $discord;
    public int $processing;
    private mixed $account;

    public function __construct(Discord $discord, int|string $botID)
    {
        $this->processing = 0;
        $this->discord = $discord;
        $this->botID = $botID;
        $this->plans = array();
        $this->refreshDate = get_future_date(DiscordProperties::SYSTEM_REFRESH_TIME);
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

    public function getAccount(): mixed
    {
        return $this->account;
    }
}