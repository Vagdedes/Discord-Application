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
                $session
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

    public static function change_username(DiscordPlan $plan,
                                           Interaction $interaction,
                                           mixed       $objects): void
    {
        $application = new Application($plan->applicationID);
        $session = $application->getAccountSession();
        $session->setCustomKey("discord", $interaction->user->id);
        $account = $session->getSession();

        if ($account->isPositiveOutcome()) {
            $account = $account->getObject();
            $username = array_shift($objects->toArray())["value"];

            // Separator

            $interaction->acknowledgeWithResponse(true);
            $interaction->updateOriginalResponse(MessageBuilder::new()->setContent(
                $account->getActions()->changeName($username)->getMessage()
            ));
        } else {
            $plan->controlledMessages->send($interaction, "0-register_or_log_in", true, true);
        }
    }

    public static function change_email(DiscordPlan $plan,
                                        Interaction $interaction,
                                        mixed       $objects): void
    {
        $application = new Application($plan->applicationID);
        $session = $application->getAccountSession();
        $session->setCustomKey("discord", $interaction->user->id);
        $account = $session->getSession();

        if ($account->isPositiveOutcome()) {
            $account = $account->getObject();
            $email = array_shift($objects->toArray())["value"];

            $interaction->acknowledgeWithResponse(true);
            $interaction->updateOriginalResponse(MessageBuilder::new()->setContent(
                $account->getEmail()->requestVerification($email)->getMessage()
            ));
        } else {
            $plan->controlledMessages->send($interaction, "0-register_or_log_in", true, true);
        }
    }

    public static function contact_form(DiscordPlan $plan,
                                        Interaction $interaction,
                                        mixed       $objects): void
    {
        $application = new Application($plan->applicationID);
        $session = $application->getAccountSession();
        $session->setCustomKey("discord", $interaction->user->id);
        $account = $session->getSession();

        if ($account->isPositiveOutcome()) {
            $account = $account->getObject();
            $cacheKey = array(
                get_client_ip_address(),
                "contact-form"
            );

            if (has_memory_cooldown($cacheKey, null, false)) {
                $response = "Please wait a few minutes before contacting us again.";
            } else {
                $objects = $objects->toArray();
                $platformsString = null;
                $accounts = $account->getAccounts()->getAdded();

                if (!empty($accounts)) {
                    $platformsString = "Accounts:\r\n";

                    foreach ($accounts as $row) {
                        $platformsString .= $row->accepted_account->name . ": " . $row->credential . "\r\n";
                    }
                    $platformsString .= "\r\n";
                }
                $id = rand(0, 2147483647);
                $email = $account->getDetail("email_address");
                $subject = strip_tags(array_shift($objects)["value"]);
                $title = get_domain() . " - $subject [ID: $id]";
                $content = "ID: $id" . "\r\n"
                    . "Subject: $subject" . "\r\n"
                    . "Email: $email" . "\r\n"
                    . "\r\n"
                    . ($platformsString !== null ? $platformsString : "")
                    . strip_tags(array_shift($objects)["value"]);

                if (services_self_email($email, $title, $content) === true) {
                    has_memory_cooldown($cacheKey, "5 minutes");
                    $response = "Thanks for taking the time to contact us.";
                } else {
                    global $email_default_email_name;
                    $response = "An error occurred, please contact us at: " . $email_default_email_name;
                }
            }
            $interaction->acknowledgeWithResponse(true);
            $interaction->updateOriginalResponse(MessageBuilder::new()->setContent($response));
        } else {
            $plan->controlledMessages->send($interaction, "0-register_or_log_in", true, true);
        }
    }
}