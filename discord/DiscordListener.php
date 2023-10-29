<?php

use Discord\Builders\MessageBuilder;
use Discord\Parts\Interactions\Interaction;

class DiscordListener
{
    private DiscordPlan $plan;
    private const
        IMPLEMENTATION = "/root/discord_bot/listeners/implementation/",
        CREATION = "/root/discord_bot/listeners/creation/";

    public function __construct(DiscordPlan $plan)
    {
        $this->plan = $plan;
    }

    public function callImplementation(Interaction $interaction,
                                       MessageBuilder $messageBuilder,
                                       ?string     $class, ?string $method,
                                       mixed       $objects = null): void
    {
        if ($class !== null && $method !== null) {
            require_once(self::IMPLEMENTATION . $this->plan->planID . "/" . $class . '.php');
            call_user_func_array(
                array($class, $method),
                array($this->plan, $interaction, $messageBuilder, $objects)
            );
        }
    }

    public function callCreation(MessageBuilder $messageBuilder,
                                 ?string        $class, ?string $method): MessageBuilder
    {
        if ($class !== null && $method !== null) {
            require_once(self::CREATION . $this->plan->planID . "/" . $class . '.php');
            return call_user_func_array(
                array($class, $method),
                array($this->plan, $messageBuilder)
            );
        } else {
            return $messageBuilder;
        }
    }
}