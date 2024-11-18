<?php

use Discord\Builders\Components\TextInput;
use Discord\Parts\Interactions\Interaction;

class AccountModalCreationListener
{

    public static function register(DiscordBot  $bot,
                                    Interaction $interaction,
                                    TextInput   $input,
                                    int         $position): TextInput
    {
        if ($position === 1) {
            $input->setValue($interaction->user->username);
        }
        return $input;
    }

    public static function log_in(DiscordBot  $bot,
                                  Interaction $interaction,
                                  TextInput   $input,
                                  int         $position): TextInput
    {
        $account = AccountMessageCreationListener::getAccountObject($interaction);
        $object = $account->getSession()->getLastKnown();

        if ($object !== null) {
            $account = $account->transform($object->account_id);

            if ($account->exists()) {
                $input->setValue($account->getDetail("email_address"));
            }
        }
        return $input;
    }
}