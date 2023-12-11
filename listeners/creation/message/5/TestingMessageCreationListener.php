<?php

use Discord\Builders\MessageBuilder;
use Discord\Parts\Interactions\Interaction;

class TestingMessageCreationListener // Name can be changed
{

    public static function test_method(DiscordPlan    $plan,
                                       ?Interaction   $interaction,
                                       MessageBuilder $messageBuilder): MessageBuilder // Name can be changed
    {
        $messageBuilder->setContent("Hello World!");
        return $messageBuilder;
    }
}