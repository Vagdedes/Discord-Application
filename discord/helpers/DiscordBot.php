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
    public DiscordInstructions $instructions;
    public DiscordCommands $commands;
    public DiscordComponent $component;
    public DiscordPersistentMessages $persistentMessages;
    public DiscordUserTickets $userTickets;
    public DiscordUserTargets $userTargets;
    public DiscordCountingChannels $countingChannels;
    public DiscordUserLevels $userLevels;
    public DiscordInviteTracker $inviteTracker;
    public DiscordInteractionRoles $interactionRoles;
    public DiscordTemporaryChannels $temporaryChannels;
    public DiscordReminderMessages $reminderMessages;
    public DiscordUserQuestionnaire $userQuestionnaire;
    public DiscordAIMessages $aiMessages;
    public DiscordStatusMessages $statusMessages;
    public DiscordUserNotes $userNotes;
    public DiscordUserGiveaways $userGiveaways;
    public DiscordChatFilteredMessages $chatFilteredMessages;
    public DiscordObjectiveChannels $objectiveChannels;
    public DiscordNotificationMessages $notificationMessages;
    private int $counter;

    public function __construct(Discord $discord, int|string $botID)
    {
        $this->counter = 0;
        $this->discord = $discord;
        $this->botID = $botID;

        $this->utilities = new DiscordUtilities($this);
        $this->listener = new DiscordListener($this);
        $this->channels = new DiscordChannels($this);
        $this->mute = new DiscordMute($this);
        $this->discordAntiExpirationThreads = new DiscordAntiExpirationThreads($this);
        $this->permissions = new DiscordPermissions($this);
        $this->faq = new DiscordFAQ($this);
        $this->joinRoles = new DiscordJoinRoles($this);
        $this->statisticsChannels = new DiscordStatisticsChannels($this);
        $this->tranferredMessages = new DiscordTransferredMessages($this);
        $this->commands = new DiscordCommands($this);
        $this->component = new DiscordComponent($this);
        $this->aiMessages = new DiscordAIMessages($this);
        $this->interactionRoles = new DiscordInteractionRoles($this);
        $this->instructions = new DiscordInstructions($this);
        $this->instructions = new DiscordInstructions($this); // Dependent on exact above
        $this->persistentMessages = new DiscordPersistentMessages($this);
        $this->userTickets = new DiscordUserTickets($this);
        $this->countingChannels = new DiscordCountingChannels($this);
        $this->userLevels = new DiscordUserLevels($this);
        $this->temporaryChannels = new DiscordTemporaryChannels($this);
        $this->reminderMessages = new DiscordReminderMessages($this);
        $this->userQuestionnaire = new DiscordUserQuestionnaire($this);
        $this->statusMessages = new DiscordStatusMessages($this);
        $this->userTargets = new DiscordUserTargets($this);
        $this->userNotes = new DiscordUserNotes($this);
        $this->inviteTracker = new DiscordInviteTracker($this);
        $this->userGiveaways = new DiscordUserGiveaways($this);
        $this->chatFilteredMessages = new DiscordChatFilteredMessages($this);
        $this->objectiveChannels = new DiscordObjectiveChannels($this);
        $this->notificationMessages = new DiscordNotificationMessages($this);

        $this->refreshDate = get_future_date(DiscordProperties::SYSTEM_REFRESH_TIME);
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
                load_sql_database();
            }
            return true;
        } else {
            return false;
        }
    }
}