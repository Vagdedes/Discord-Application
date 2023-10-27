<?php

use Discord\Builders\MessageBuilder;
use Discord\Parts\Interactions\Interaction;

class AccountImplementationListener
{

    public static function my_account(DiscordPlan $plan,
                                      Interaction $interaction,
                                      mixed       $objects): void
    {
        $interaction->respondWithMessage(
            MessageBuilder::new()->setContent(json_encode($objects)),
            true
        );
    }
}