<?php

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

    //todo keywords to trigger if not empty
    //todo use of ai with specific return constants/enums for handling
}