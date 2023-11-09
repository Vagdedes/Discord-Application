<?php

class DiscordUtilities
{

    private DiscordPlan $plan;

    public function __construct(DiscordPlan $plan)
    {
        $this->plan = $plan;
    }

    public function getUsername(int|string $userID): string
    {
        $users = $this->plan->discord->users->getIterator();
        return $users[$userID]?->username ?? $userID;
    }
}