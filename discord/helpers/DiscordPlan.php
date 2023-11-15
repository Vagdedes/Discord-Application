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
    public DiscordControlledMessages $controlledMessages;
    public DiscordTicket $ticket;
    public DiscordMessageRefresh $messageRefresh;
    public DiscordPermissions $permissions;
    public DiscordUtilities $utilities;
    public DiscordBot $bot;
    public DiscordTargetedMessage $target;
    public DiscordCounting $counting;
    public DiscordPoll $poll;
    public DiscordLevel $level;
    public DiscordCheaperChatAI $cheaperChatAI;
    public DiscordInviteTracker $inviteTracker;
    public DiscordReactionRoles $reactionRoles;
    public DiscordTemporaryChannel $temporaryChannel;
    public DiscordSocialAlerts $socialAlerts;
    public DiscordMessageReminders $messageReminders;
    public DiscordQuestionnaire $questionnaire;
    public DiscordControlledChannels $controlledChannels;
    public DiscordAI $ai;
    public DiscordStatus $status;
    public DiscordLocations $locations;

    public function __construct(Discord    $discord,
                                DiscordBot $bot,
                                int|string $botID, int|string $planID)
    {
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

        $this->bot = $bot;
        $this->listener = new DiscordListener($this);
        $this->instructions = new DiscordInstructions($this);
        $this->conversation = new DiscordConversation($this);
        $this->moderation = new DiscordModeration($this);
        $this->limits = new DiscordLimits($this);
        $this->commands = new DiscordCommands($this);
        $this->component = new DiscordComponent($this);
        $this->controlledMessages = new DiscordControlledMessages($this);
        $this->ticket = new DiscordTicket($this);
        $this->messageRefresh = new DiscordMessageRefresh($this);
        $this->permissions = new DiscordPermissions($this);
        $this->utilities = new DiscordUtilities($this);
        $this->target = new DiscordTargetedMessage($this);
        $this->counting = new DiscordCounting($this);
        $this->poll = new DiscordPoll($this);
        $this->level = new DiscordLevel($this);
        $this->cheaperChatAI = new DiscordCheaperChatAI($this);
        $this->inviteTracker = new DiscordInviteTracker($this);
        $this->reactionRoles = new DiscordReactionRoles($this);
        $this->temporaryChannel = new DiscordTemporaryChannel($this);
        $this->socialAlerts = new DiscordSocialAlerts($this);
        $this->messageReminders = new DiscordMessageReminders($this);
        $this->questionnaire = new DiscordQuestionnaire($this);
        $this->controlledChannels = new DiscordControlledChannels($this);
        $this->ai = new DiscordAI($this);
        $this->status = new DiscordStatus($this);
        $this->locations = new DiscordLocations($this);
    }

}