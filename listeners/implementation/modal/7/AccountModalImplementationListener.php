<?php

use Discord\Builders\MessageBuilder;
use Discord\Parts\Interactions\Interaction;

class AccountModalImplementationListener
{

    public static function register(DiscordPlan $plan,
                                    Interaction $interaction,
                                    mixed       $objects): void
    {
        $application = new Application($plan->applicationID);
        $session = $application->getAccountSession();
        $session->setCustomKey("discord", $interaction->user->id);
        $account = $session->getSession();

        if ($account->isPositiveOutcome()) {
            $plan->controlledMessages->send($interaction, "0-logged_in", true);
        } else {
            $objects = $objects->toArray();
            $email = array_shift($objects)["value"];
            $username = array_shift($objects)["value"];
            $password = array_shift($objects)["value"];
            $accountRegistry = $application->getAccountRegistry(
                $email,
                $password,
                $username,
                null,
                null,
                null,
                AccountRegistry::DEFAULT_WEBHOOK
            )->getOutcome();

            if ($accountRegistry->isPositiveOutcome()) {
                $plan->controlledMessages->send($interaction, "0-logged_in", true);
            } else {
                $interaction->acknowledgeWithResponse(true);
                $interaction->updateOriginalResponse(MessageBuilder::new()->setContent(
                    $accountRegistry->getMessage()
                ));
            }
        }
    }

    public static function log_in(DiscordPlan $plan,
                                  Interaction $interaction,
                                  mixed       $objects): void
    {
        $application = new Application($plan->applicationID);
        $session = $application->getAccountSession();
        $session->setCustomKey("discord", $interaction->user->id);
        $account = $session->getSession();

        if ($account->isPositiveOutcome()) {
            $plan->controlledMessages->send($interaction, "0-logged_in", true);
        } else {
            $objects = $objects->toArray();
            $email = array_shift($objects)["value"];
            $password = array_shift($objects)["value"];

            // Separator

            $account = $application->getAccount(null, $email);

            if ($account->exists()) {
                $result = $account->getActions()->logIn($password, $session, false);

                if ($result->isPositiveOutcome()) {
                    $response = null;
                } else {
                    $response = $result->getMessage();
                }
            } else {
                $response = "Account with this email does not exist.";
            }

            // Separator

            if ($response === null) {
                $plan->controlledMessages->send($interaction, "0-logged_in", true);
            } else {
                $interaction->acknowledgeWithResponse(true);
                $interaction->updateOriginalResponse(MessageBuilder::new()->setContent(
                    $response
                ));
            }
        }
    }
}