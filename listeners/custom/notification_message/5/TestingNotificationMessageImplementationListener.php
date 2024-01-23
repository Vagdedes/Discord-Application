<?php

use Discord\Builders\MessageBuilder;

class TestingNotificationMessageImplementationListener // Name can be changed
{

    public static function test_method(DiscordPlan $plan,
                                       string      $message,
                                       object      $object): string // Name can be changed
    {
        return $message;
    }
}