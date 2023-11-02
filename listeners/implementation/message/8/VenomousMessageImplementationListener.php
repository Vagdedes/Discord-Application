<?php

use Discord\Builders\MessageBuilder;
use Discord\Parts\Interactions\Interaction;

class VenomousMessageImplementationListener
{
    public static function initial_selection(DiscordPlan    $plan,
                                             Interaction    $interaction,
                                             MessageBuilder $messageBuilder,
                                             mixed          $objects): void
    {
        $objects = $objects[0]->getValue();

        switch ($objects) {
            case "purchase":
                $plan->ticket->open($interaction, "8-order");
                break;
            default:
                $interaction->respondWithMessage(
                    MessageBuilder::new()->setContent($objects),
                    true
                );
                break;
        }
    }
}
