<?php

class DiscordUserGiveaways
{
    private DiscordPlan $plan;

    public function __construct(DiscordPlan $plan)
    {
        $this->plan = $plan;
    }

    //todo join-giveaway, leave-giveaway, initiate-giveaway, stop-giveaway command
    //todo manual/scheduled (manual/automatic join) giveaways
    //todo giveaway role requirements
    //todo poll role, permission or both requirements
}