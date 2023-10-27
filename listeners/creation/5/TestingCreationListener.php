<?php

use Discord\Builders\MessageBuilder;
use Discord\Discord;

class TestingCreationListener // Name can be changed
{

    public static function test_method(DiscordPlan    $plan,
                                       MessageBuilder $messageBuilder): MessageBuilder // Name can be changed
    {
        $messageBuilder->setContent("Hello World!");
        return $messageBuilder;
    }
}