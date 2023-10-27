<?php

use Discord\Builders\MessageBuilder;
use Discord\Discord;

class AccountCreationListener // Name can be changed
{

    public static function my_account(Discord        $discord,
                                       MessageBuilder $messageBuilder): MessageBuilder // Name can be changed
    {
        var_dump("test");
        return $messageBuilder;
    }
}