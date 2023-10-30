<?php

use Discord\Parts\Interactions\Interaction;

class TestingModalCreationListener // Name can be changed
{

    public static function test_method(DiscordPlan $plan,
                                       Interaction $interaction,
                                       array       $actionRows): array // Name can be changed
    {
        return $actionRows;
    }
}