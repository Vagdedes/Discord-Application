<?php

class DiscordUserPolls
{
    private DiscordPlan $plan;

    public function __construct(DiscordPlan $plan)
    {
        $this->plan = $plan;
    }

    //todo max 20 choices (4x5 buttons, 20 select options, 20 reactions)
    //todo create-poll, delete-poll, pick-poll-choice, unpick-poll-choice commands
}