<?php

use Discord\Builders\Components\TextInput;
use Discord\Parts\Interactions\Interaction;

class TestingModalCreationListener // Name can be changed
{

    public static function test_method(DiscordBot  $bot,
                                       Interaction $interaction,
                                       TextInput   $input,
                                       int         $position): TextInput // Name can be changed
    {
        return $input;
    }
}