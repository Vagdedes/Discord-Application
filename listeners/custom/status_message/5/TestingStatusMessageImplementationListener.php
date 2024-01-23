<?php

use Discord\Builders\MessageBuilder;
use Discord\Parts\Channel\Channel;
use Discord\Parts\User\Member;
use DiscordPlan;

class TestingStatusMessageImplementationListener // Name can be changed
{

    public static function test_method(DiscordPlan    $plan,
                                       Channel        $channel,
                                       Member         $member,
                                       MessageBuilder $messageBuilder,
                                       object         $object,
                                       int            $case): MessageBuilder // Name can be changed
    {
        return $messageBuilder;
    }
}