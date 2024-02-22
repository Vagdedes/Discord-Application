<?php

use Discord\Builders\MessageBuilder;
use Discord\Parts\Interactions\Interaction;

class DiscordFAQ
{
    private DiscordPlan $plan;

    public function __construct(DiscordPlan $plan)
    {
        $this->plan = $plan;
    }

    public function addOrEdit(Interaction $interaction,
                              string      $question, string $answer): ?string
    {
        return null;
    }

    public function delete(Interaction $interaction,
                           string      $question): ?string
    {
        return null;
    }

    public function list(Interaction $interaction): MessageBuilder
    {
        $builder = new MessageBuilder();
        return $builder;
    }
}