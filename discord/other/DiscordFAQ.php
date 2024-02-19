<?php

class DiscordFAQ
{
    private DiscordPlan $plan;

    public function __construct(DiscordPlan $plan)
    {
        $this->plan = $plan;
    }

    // create faq, delete faq, update faq, list faq
}