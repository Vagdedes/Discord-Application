<?php

class DiscordWebAttachments
{
    private DiscordPlan $plan;

    public function __construct(DiscordPlan $plan)
    {
        $this->plan = $plan;
    }

    // create-web-attachment, delete-web-attachment, update-web-attachment, list-web-attachment
}