<?php

use Discord\Discord;
use Discord\Parts\Interactions\Interaction;

class DiscordListener
{
    private DiscordPlan $plan;

    public function __construct(DiscordPlan $plan)
    {
        $this->plan = $plan;
    }

    public function call(Interaction $interaction,
                         ?string     $class, ?string $method,
                         mixed       $objects = null): mixed
    {
        if ($method !== null) {
            require_once('/root/discord_bot/listeners/' . $this->plan->planID . "/" . $method . '.php');
            return call_user_func_array(
                $class !== null ? array($class, $method) : $method,
                array($this->plan->discord, $interaction, $objects)
            );
        } else {
            return false; // False because the PHP method returns false on error
        }
    }
}