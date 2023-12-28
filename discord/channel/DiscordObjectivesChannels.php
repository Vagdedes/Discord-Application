<?php

use Discord\Parts\Channel\Channel;
use Discord\Parts\User\Member;
use Discord\Parts\User\User;
use Discord\Parts\WebSockets\VoiceStateUpdate;

class DiscordObjectivesChannels
{
    private DiscordPlan $plan;
    private array $channels;
    public int $ignoreDeletion;

    public function __construct(DiscordPlan $plan)
    {
        $this->ignoreDeletion = 0;
        $this->plan = $plan;
    }

    //todo creation channel
    //todo note channel on deletion (embed messages) [listener]
}