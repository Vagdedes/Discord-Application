<?php

class DiscordTransferredMessages
{
    private DiscordPlan $plan;
    private array $channels;

    public function __construct(DiscordPlan $plan)
    {
        $this->plan = $plan;
    }

    //todo channel/thread inputs
    //todo channel/thread outputs
}