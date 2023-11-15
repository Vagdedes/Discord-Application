<?php

use Discord\Builders\MessageBuilder;
use Discord\Parts\Channel\Channel;
use Discord\Parts\Thread\Thread;

class TestingTargetImplementationListener // Name can be changed
{

    public static function test_method(DiscordPlan $plan,
                                       Channel     $channel,
                                       Thread      $thread,
                                       mixed       $object): void // Name can be changed
    {
        $channel->sendMessage(
            MessageBuilder::new()->setContent(json_encode($object)),
        );
        $thread->sendMessage(
            MessageBuilder::new()->setContent(json_encode($object)),
        );
    }
}