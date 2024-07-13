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

    public static function log_in(DiscordPlan $plan,
                                  Interaction $interaction,
                                  TextInput   $input,
                                  int         $position): TextInput
    {
        $account = AccountMessageCreationListener::getAccountObject($interaction, $plan);
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