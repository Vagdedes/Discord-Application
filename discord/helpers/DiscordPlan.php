<?php

class DiscordPlan
{
    public int $planID;
    public string $name, $creationDate;
    public ?string $description, $expirationDate, $creationReason, $expirationReason;
    public DiscordInstructions $instructions;
    public DiscordCommands $commands;
    public DiscordListener $listener;
    public DiscordComponent $component;
    public DiscordPersistentMessages $persistentMessages;
    public DiscordUserTickets $userTickets;
    public DiscordUtilities $utilities;
    public DiscordBot $bot;
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

    public function __construct(DiscordBot $bot, int|string $planID)
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
        $this->planID = (int)$query->id;
        $this->name = $query->name;
        $this->description = $query->description;
        $this->creationDate = $query->creation_date;
        $this->creationReason = $query->creation_reason;
        $this->expirationDate = $query->expiration_date;
        $this->expirationReason = $query->expiration_reason;

        $this->utilities = new DiscordUtilities($this);

        $this->listener = new DiscordListener($this);
        $this->commands = new DiscordCommands($this);
        $this->component = new DiscordComponent($this);

        $this->aiMessages = new DiscordAIMessages($this);
        $this->instructions = new DiscordInstructions($this); // Dependent on above

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
        $this->interactionRoles = new DiscordInteractionRoles($this);
    }

}