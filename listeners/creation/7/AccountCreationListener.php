<?php

use Discord\Builders\MessageBuilder;
use Discord\Discord;

class AccountCreationListener // Name can be changed
{

    public static function my_account(Discord        $discord,
                                      MessageBuilder $messageBuilder): MessageBuilder // Name can be changed
    {
        $application = new Application(null);
        $products = $application->getAccount(0)->getProduct()->find(null, false);
        var_dump($products);
        return $messageBuilder;
    }
}