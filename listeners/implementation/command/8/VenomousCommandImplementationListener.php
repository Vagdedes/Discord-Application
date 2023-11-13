<?php

use Discord\Builders\MessageBuilder;
use Discord\Parts\Interactions\Interaction;

class VenomousCommandImplementationListener // Name can be changed
{

    public static function test_method(DiscordPlan $plan,
                                       Interaction $interaction,
                                       object      $command): void // Name can be changed
    {
        $plan->utilities->acknowledgeMessage(
            $interaction,
            MessageBuilder::new()->setContent(json_encode($command)),
            true
        );
    }
}