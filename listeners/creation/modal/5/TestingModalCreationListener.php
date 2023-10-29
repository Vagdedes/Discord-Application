<?php

class TestingModalCreationListener // Name can be changed
{

    public static function test_method(DiscordPlan $plan,
                                       array       $actionRows): array // Name can be changed
    {
        return $actionRows;
    }
}