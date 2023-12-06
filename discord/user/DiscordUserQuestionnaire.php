<?php

class DiscordUserQuestionnaire
{
    private DiscordPlan $plan;

    public function __construct(DiscordPlan $plan)
    {
        $this->plan = $plan;
    }

    //todo repeat-question, take-questionnaire, quit-questionnaire command
}