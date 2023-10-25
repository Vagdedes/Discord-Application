<?php

use Discord\Builders\MessageBuilder;
use Discord\Discord;
use Discord\Parts\Interactions\Interaction;

class TestingListener
{

    public static function test_method(Discord $discord, Interaction $interaction,
                                       mixed $objects): int
    {
        $interaction->respondWithMessage(
            MessageBuilder::new()->setContent(json_encode($objects)),
            true
        );
        return 1;
    }
}