<?php

use Discord\Builders\MessageBuilder;
use Discord\Parts\Interactions\Interaction;

class TestingMessageImplementationListener // Name can be changed
{

    public static function test_method(DiscordPlan    $plan,
                                       Interaction    $interaction,
                                       MessageBuilder $messageBuilder,
                                       mixed          $objects): void // Name can be changed
    {
        $plan->conversation->acknowledgeMessage(
            $interaction,
            MessageBuilder::new()->setContent(json_encode($objects)),
            true
        );
    }
}