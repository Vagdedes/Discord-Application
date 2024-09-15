<?php

/*
 * Bot Features:
 * Custom Mute
 * Custom Logs
 * Custom Commands
 * -
 * Invite Tracker
 * Frequently Asked Questions (FAQ)
 * -
 * Reaction Roles
 * Join Roles
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
 * User Suggestions
 */

use Discord\Discord;

class DiscordBot
{
    public int|string $botID;
    public array $plans;
    private string $refreshDate;
    public Discord $discord;
    public DiscordUtilities $utilities;
    public DiscordMute $mute;
    public DiscordAntiExpirationThreads $discordAntiExpirationThreads;
    public DiscordPermissions $permissions;
    public DiscordFAQ $faq;
    public DiscordListener $listener;
    public DiscordJoinRoles $joinRoles;
    public DiscordStatisticsChannels $statisticsChannels;
    public DiscordTransferredMessages $tranferredMessages;
    public DiscordChannels $channels;
    private int $counter;

    public function __construct(Discord $discord, int|string $botID)
    {
        $this->counter = 0;
        $this->discord = $discord;
        $this->botID = $botID;
        $this->plans = array();

        $this->utilities = new DiscordUtilities($this->discord);
        $this->listener = new DiscordListener($this->discord);
        $this->channels = new DiscordChannels($this->discord);

        $this->mute = new DiscordMute($this);
        $this->discordAntiExpirationThreads = new DiscordAntiExpirationThreads($this);
        $this->permissions = new DiscordPermissions($this);
        $this->faq = new DiscordFAQ($this);
        $this->joinRoles = new DiscordJoinRoles($this);
        $this->statisticsChannels = new DiscordStatisticsChannels($this);
        $this->tranferredMessages = new DiscordTransferredMessages($this);

        $this->load();
        $this->refreshDate = get_future_date(DiscordProperties::SYSTEM_REFRESH_TIME);
    }

    public function getPlan(int|string $planID): ?DiscordPlan
    {
        return $this->plans[(int)$planID] ?? null;
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
            $logger->logError(null, "Found no plans for bot with ID: " . $this->botID);
        } else {
            foreach ($query as $row) {
                $this->plans[(int)$row->id] = new DiscordPlan(
                    $this,
                    $row->id
                );
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
            create_sql_connection();

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
}