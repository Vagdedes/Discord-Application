<?php

class DiscordInteractionRoles
{
    private DiscordPlan $plan;

    public function __construct(DiscordPlan $plan)
    {
        $this->plan = $plan;
    }

    //todo max 20 choices (4x5 buttons, 20 select options, 20 reactions)
}