<?php

use Discord\Builders\MessageBuilder;
use Discord\Parts\Interactions\Interaction;

class DiscordListener
{
    private DiscordPlan $plan;
    private const
        IMPLEMENTATION_MESSAGE = "/root/discord_bot/listeners/implementation/message/",
        IMPLEMENTATION_MODAL = "/root/discord_bot/listeners/implementation/modal/",
        CREATION_MESSAGE = "/root/discord_bot/listeners/creation/message/",
        CREATION_MODAL = "/root/discord_bot/listeners/creation/modal/";

    public function __construct(DiscordPlan $plan)
    {
        $this->plan = $plan;
    }

    public function callMessageImplementation(Interaction    $interaction,
                                              MessageBuilder $messageBuilder,
                                              ?string        $class, ?string $method,
                                              mixed          $objects = null): void
    {
        if ($class !== null && $method !== null) {
            require_once(self::IMPLEMENTATION_MESSAGE . $this->plan->planID . "/" . $class . '.php');
            call_user_func_array(
                array($class, $method),
                array($this->plan, $interaction, $messageBuilder, $objects)
            );
        }
    }

    public function callModalImplementation(Interaction $interaction,
                                            ?string     $class, ?string $method,
                                            mixed       $objects = null): void
    {
        if ($class !== null && $method !== null) {
            require_once(self::IMPLEMENTATION_MODAL . $this->plan->planID . "/" . $class . '.php');
            call_user_func_array(
                array($class, $method),
                array($this->plan, $interaction, $objects)
            );
        }
    }

    public function callMessageBuilderCreation(MessageBuilder $messageBuilder,
                                               ?string        $class, ?string $method): MessageBuilder
    {
        if ($class !== null && $method !== null) {
            require_once(self::CREATION_MESSAGE . $this->plan->planID . "/" . $class . '.php');
            return call_user_func_array(
                array($class, $method),
                array($this->plan, $messageBuilder)
            );
        } else {
            return $messageBuilder;
        }
    }

    public function callModalCreation(array   $actionRows,
                                      ?string $class, ?string $method): array
    {
        if ($class !== null && $method !== null) {
            require_once(self::CREATION_MODAL . $this->plan->planID . "/" . $class . '.php');
            return call_user_func_array(
                array($class, $method),
                array($this->plan, $actionRows)
            );
        } else {
            return $actionRows;
        }
    }
}