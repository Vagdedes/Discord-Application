<?php

use Discord\Discord;

class DiscordPlan
{
    public int $planID, $botID;
    public ?int $applicationID, $family;
    public string $name, $creationDate;
    public ?string $description, $expirationDate, $creationReason, $expirationReason;
    public Discord $discord;
    public DiscordInstructions $instructions;
    public DiscordConversation $conversation;
    public DiscordModeration $moderation;
    public DiscordLimits $limits;
    public DiscordCommands $commands;
    public DiscordListener $listener;
    public DiscordComponent $component;
    public DiscordPersistentMessages $persistentMessages;
    public DiscordUserTickets $userTickets;
    public DiscordAntiExpirationThreads $discordAntiExpirationThreads;
    public DiscordPermissions $permissions;
    public DiscordUtilities $utilities;
    public DiscordBot $bot;
    public DiscordUserTargets $userTargets;
    public DiscordCountingChannels $countingChannels;
    public DiscordReactionPolls $reactionPolls;
    public DiscordUserLevels $userLevels;
    public DiscordInviteTracker $inviteTracker;
    public DiscordReactionRoles $reactionRoles;
    public DiscordTemporaryChannels $temporaryChannels;
    public DiscordSocialAlerts $socialAlerts;
    public DiscordReminderMessages $reminderMessages;
    public DiscordUserQuestionnaire $userQuestionnaire;
    public DiscordStatisticsChannels $statisticsChannels;
    public DiscordAIMessages $aiMessages;
    public DiscordStatusMessages $statusMessages;
    public DiscordLocations $locations;
    public DiscordUserNotes $userNotes;

    public function __construct(Discord    $discord,
                                DiscordBot $bot,
                                int|string $botID, int|string $planID)
    {
        $this->bot = $bot;

        $query = get_sql_query(
            BotDatabaseTable::BOT_PLANS,
            null,
            array(
                array("id", $planID),
            ),
            null,
            1
        )[0];
        $this->discord = $discord;
        $this->botID = $botID;
        $this->planID = (int)$query->id;
        $this->family = $query->family === null ? null : (int)$query->family;
        $this->applicationID = $query->application_id === null ? null : (int)$query->application_id;
        $this->name = $query->name;
        $this->description = $query->description;
        $this->creationDate = $query->creation_date;
        $this->creationReason = $query->creation_reason;
        $this->expirationDate = $query->expiration_date;
        $this->expirationReason = $query->expiration_reason;

        $this->utilities = $bot->utilities;

        $this->locations = new DiscordLocations($this);
        $this->listener = new DiscordListener($this);
        $this->instructions = new DiscordInstructions($this);
        $this->conversation = new DiscordConversation($this);
        $this->limits = new DiscordLimits($this);
        $this->commands = new DiscordCommands($this);
        $this->component = new DiscordComponent($this);
        $this->permissions = new DiscordPermissions($this);

        $this->moderation = new DiscordModeration($this);
        $this->persistentMessages = new DiscordPersistentMessages($this);
        $this->userTickets = new DiscordUserTickets($this);
        $this->countingChannels = new DiscordCountingChannels($this);
        $this->reactionPolls = new DiscordReactionPolls($this);
        $this->userLevels = new DiscordUserLevels($this);
        $this->reactionRoles = new DiscordReactionRoles($this);
        $this->temporaryChannels = new DiscordTemporaryChannels($this);
        $this->socialAlerts = new DiscordSocialAlerts($this);
        $this->reminderMessages = new DiscordReminderMessages($this);
        $this->userQuestionnaire = new DiscordUserQuestionnaire($this);
        $this->aiMessages = new DiscordAIMessages($this);
        $this->statusMessages = new DiscordStatusMessages($this);
        $this->userTargets = new DiscordUserTargets($this);
        $this->discordAntiExpirationThreads = new DiscordAntiExpirationThreads($this);
        $this->userNotes = new DiscordUserNotes($this);
        $this->inviteTracker = new DiscordInviteTracker($this);
        $this->statisticsChannels = new DiscordStatisticsChannels($this);
    }

}