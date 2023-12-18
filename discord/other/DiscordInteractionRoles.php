<?php

use Discord\Builders\MessageBuilder;

class DiscordInteractionRoles
{
    private DiscordPlan $plan;

    public function __construct(DiscordPlan $plan)
    {
        $this->plan = $plan;
    }

    //todo max 20 choices (5x5 buttons, 20 select options)

    public function process(MessageBuilder $messageBuilder): MessageBuilder {
        return $messageBuilder;
    }
}