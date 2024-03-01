<?php

use Discord\Builders\MessageBuilder;
use Discord\Parts\Interactions\Interaction;

class CommandImplementationListener
{

    public static function user_info(DiscordPlan $plan,
                                     Interaction $interaction,
                                     object      $command): void
    {
        $account = new Account($plan->applicationID);
        $account->getSession()->setCustomKey("discord", $interaction->data?->resolved?->users?->first()?->id);
        $object = $account->getSession()->getLastKnown();
        $message = new MessageBuilder();

        if ($object !== null) {
            $account = $account->getNew($object->account_id);

            if ($account->exists()) {
                $message->setContent(
                    AccountMessageCreationListener::IDEALISTIC_URL
                    . "/contents/?path=account/panel&platform=1&id="
                    . $account->getDetail("email_address")
                );
            } else {
                $message->setContent("Account not found.");
            }
        } else {
            $message->setContent("Object not found.");
        }
        $plan->utilities->acknowledgeCommandMessage(
            $interaction,
            $message,
            true
        );
    }
}