<?php

use Discord\Parts\Channel\Message;

class DiscordChatFilteredMessages
{
    private DiscordPlan $plan;
    private array $channels;
    public int $ignoreDeletion;

    public function __construct(DiscordPlan $plan)
    {
        $this->ignoreDeletion = 0;
        $this->plan = $plan;
    }

    public function run(Message $message): ?string
    {
        return false;
    }
}