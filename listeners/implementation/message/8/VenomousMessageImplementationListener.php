<?php

use Discord\Builders\MessageBuilder;
use Discord\Parts\Interactions\Interaction;

class  VenomousMessageImplementationListener
{
    public static function initial_selection(DiscordPlan    $plan,
                                             Interaction    $interaction,
                                             MessageBuilder $messageBuilder,
                                             mixed          $objects): void
    {
        $objects = $objects[0]->getValue();
        $interaction->respondWithMessage(
            MessageBuilder::new()->setContent($objects),
            true
        );
    }
}
