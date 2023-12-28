<?php

class DiscordMute
{
    private DiscordPlan $plan;
    private array $channels;

    public function __construct(DiscordPlan $plan)
    {
        $this->plan = $plan;
    }

    //todo voice & chat mute but separate
}