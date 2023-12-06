<?php

class DiscordPolls
{
    private DiscordPlan $plan;

    public function __construct(DiscordPlan $plan)
    {
        $this->plan = $plan;
    }

    //todo max 20 choices (4x5 buttons, 20 select options, 20 reactions)
    //todo pick-poll-choice, unpick-poll-choice commands
    //todo public & private or both choice polls (public = interactions, private = commands)
    //todo poll role, permission or both requirements
}