<?php

use Discord\Builders\MessageBuilder;

class TestingNotificationMessageImplementationListener // Name can be changed
{

    public static function test_method(DiscordPlan    $plan,
                                       MessageBuilder $message,
                                       object         $object): MessageBuilder // Name can be changed
    {
        return $message;
    }
}