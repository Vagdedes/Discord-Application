<?php

use Discord\Builders\MessageBuilder;
use Discord\Discord;
use Discord\Parts\Interactions\Interaction;

class TestingListener // Name can be changed
{

    public static function test_method(Discord $discord, Interaction $interaction,
                                       mixed   $objects): void // Name can be changed
    {
        $interaction->respondWithMessage(
            MessageBuilder::new()->setContent(json_encode($objects)),
            true
        );
    }
}