<?php

use Discord\Builders\MessageBuilder;
use Discord\Parts\Channel\Channel;
use Discord\Parts\Thread\Thread;
use DiscordPlan;

class TestingReminderMessageImplementationListener // Name can be changed
{

    public static function test_method(DiscordPlan    $plan,
                                       Channel|Thread $channel,
                                       MessageBuilder $messageBuilder,
                                       object         $object): MessageBuilder // Name can be changed
    {
        return $messageBuilder;
    }
}