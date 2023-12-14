<?php
class DiscordTemporaryChannels
{
    private DiscordPlan $plan;

    public function __construct(DiscordPlan $plan)
    {
        $this->plan = $plan;
    }

    //todo add-channel-owner, remove-channel-owner, ban-channel-user, unban-channel-user commands

    public function trackJoin() {

    }

    public function trackLeave() {

    }

    public function trackMove() {

    }


}