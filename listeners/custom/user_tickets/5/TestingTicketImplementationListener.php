<?php

use Discord\Builders\MessageBuilder;
use Discord\Parts\Interactions\Interaction;
use DiscordPlan;

class TestingTicketImplementationListener // Name can be changed
{

    public static function test_method(DiscordPlan $plan,
                                       Interaction $interaction,
                                       mixed       $objects): void // Name can be changed
    {
        $plan->utilities->acknowledgeMessage(
            $interaction,
            MessageBuilder::new()->setContent(json_encode($objects)),
            true
        );
    }
}