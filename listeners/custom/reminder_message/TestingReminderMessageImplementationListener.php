<?php

use Discord\Builders\MessageBuilder;
use Discord\Parts\Channel\Channel;
use Discord\Parts\Thread\Thread;

class TestingReminderMessageImplementationListener // Name can be changed
{

    public static function test_method(DiscordBot     $bot,
                                       Channel|Thread $channel,
                                       MessageBuilder $messageBuilder,
                                       object         $object): MessageBuilder // Name can be changed
    {
        return $messageBuilder;
    }
}