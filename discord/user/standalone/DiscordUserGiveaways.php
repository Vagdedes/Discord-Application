<?php

class DiscordUserGiveaways
{
    private DiscordPlan $plan;

    public function __construct(DiscordPlan $plan)
    {
        $this->plan = $plan;
    }

    //todo create-giveaway, delete-giveaway, start-giveaway, end-giveaway, join-giveaway, leave-giveaway commands
}