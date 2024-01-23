<?php

use Discord\Parts\Channel\Channel;
use Discord\Parts\Guild\Guild;
use DiscordPlan;

class TestingChannelStatisticsImplementationListener // Name can be changed
{

    public static function test_method(DiscordPlan $plan,
                                       Guild       $guild,
                                       ?Channel    $channel,
                                       string      $name,
                                       object      $object): void // Name can be changed
    {

    }
}