<?php

use Discord\Builders\MessageBuilder;
use Discord\Parts\Interactions\Interaction;

class TestingImplementationListener // Name can be changed
{

    public static function test_method(DiscordPlan $plan,
                                       Interaction $interaction,
                                       MessageBuilder $messageBuilder,
                                       mixed       $objects): void // Name can be changed
    {
        $interaction->respondWithMessage(
            MessageBuilder::new()->setContent(json_encode($objects)),
            true
        );
    }
}