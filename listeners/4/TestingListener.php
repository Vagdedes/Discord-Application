<?php

use Discord\Discord;
use Discord\Parts\Interactions\Interaction;

class TestingListener
{

    public static function test_method(Discord $discord, Interaction $interaction, ?array $objects): int
    {
        var_dump($objects);
        return 1;
    }
}