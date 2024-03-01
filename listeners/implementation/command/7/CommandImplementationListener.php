<?php

use Discord\Builders\MessageBuilder;
use Discord\Parts\Interactions\Interaction;

class CommandImplementationListener
{

    public static function user_info(DiscordPlan $plan,
                                     Interaction $interaction,
                                     object      $command): void
    {
        $interaction->acknowledge()->done(function () use ($plan, $interaction) {
            $account = new Account($plan->applicationID);
            $object = $account->getSession()->getLastKnown();
            $message = new MessageBuilder();

            if ($object !== null) {
                $message->setContent("https://www.idealistic.ai/contents/?path=account/panel&platform=0&id="
                    . $account->getDetail("email_address"));
            } else {
                $message->setContent("Account not found.");
            }
            $interaction->sendFollowUpMessage($message);
        });
    }
}