<?php

use Discord\Builders\Components\TextInput;
use Discord\Parts\Interactions\Interaction;

class AccountModalCreationListener
{

    public static function register(DiscordPlan $plan,
                                       Interaction $interaction,
                                       TextInput   $input,
                                       int         $position): TextInput
    {
        if ($position === 1) {
            $input->setValue($interaction->user->username);
        }
        return $input;
    }
}