<?php

use Discord\Builders\MessageBuilder;
use Discord\Parts\Channel\Channel;
use Discord\Parts\User\Member;
use DiscordBot;

class TestingStatusMessageImplementationListener // Name can be changed
{

    public static function test_method(DiscordBot     $bot,
                                       Channel        $channel,
                                       Member         $member,
                                       MessageBuilder $messageBuilder,
                                       object         $object,
                                       int            $case): MessageBuilder // Name can be changed
    {
        return $messageBuilder;
    }
}