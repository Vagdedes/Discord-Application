<?php

use Discord\Builders\MessageBuilder;

class TestingNotificationMessageImplementationListener // Name can be changed
{

    public static function test_method(DiscordBot     $bot,
                                       MessageBuilder $message,
                                       object         $object): MessageBuilder // Name can be changed
    {
        return $message;
    }
}