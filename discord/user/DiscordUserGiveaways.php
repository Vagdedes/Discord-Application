<?php

class DiscordUserGiveaways
{
    private DiscordPlan $plan;

    public function __construct(DiscordPlan $plan)
    {
        $this->plan = $plan;
    }

    //todo join-giveaway, leave-giveaway, start-giveaway, stop-giveaway command
}