<?php

use Discord\Builders\MessageBuilder;
use Discord\Parts\Channel\Message;
use Discord\Parts\Interactions\Interaction;

class TestingCommandImplementationListener // Name can be changed
{

    public static function test_method(DiscordPlan $plan,
                                       Interaction|Message $interaction,
                                       object      $command): void // Name can be changed
    {
        $plan->utilities->acknowledgeMessage(
            $interaction,
            MessageBuilder::new()->setContent(@json_encode($command)),
            true
        );
    }
}