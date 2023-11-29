<?php

use Discord\Builders\MessageBuilder;
use Discord\Parts\Interactions\Interaction;

class AccountModalImplementationListener
{

    public static function register(DiscordPlan $plan,
                                    Interaction $interaction,
                                    mixed       $objects): void
    {
        $account = new Account($plan->applicationID);
        $session = $account->getSession();
        $session->setCustomKey("discord", $interaction->user->id);
        $account = $session->getSession();

        if ($account->isPositiveOutcome()) {
            $plan->persistentMessages->send($interaction, "0-logged_in", true);
        } else {
            $account = $account->getObject();
            $objects = $objects->toArray();
            $email = array_shift($objects)["value"];
            $username = array_shift($objects)["value"];
            $password = array_shift($objects)["value"];

            $interaction->acknowledge()
                ->done(function () use ($interaction, $plan, $account, $email, $password, $username, $session) {
                    $accountRegistry = $account->getRegistry()->create(
                        $email,
                        $password,
                        $username,
                        null,
                        null,
                        null,
                        $session
                    );

                    if ($accountRegistry->isPositiveOutcome()) {
                        $interaction->sendFollowUpMessage(
                            $plan->persistentMessages->get($interaction, "0-logged_in"),
                            true
                        );
                    } else {
                        $interaction->sendFollowUpMessage(MessageBuilder::new()->setContent(
                            $accountRegistry->getMessage()
                        ), true);
                    }
                });
        }
    }

    public static function log_in(DiscordPlan $plan,
                                  Interaction $interaction,
                                  mixed       $objects): void
    {
        $account = new Account($plan->applicationID);
        $session = $account->getSession();
        $session->setCustomKey("discord", $interaction->user->id);
        $account = $session->getSession();

        if ($account->isPositiveOutcome()) {
            $plan->persistentMessages->send($interaction, "0-logged_in", true);
        } else {
            $account = $account->getObject();
            $objects = $objects->toArray();
            $email = array_shift($objects)["value"];
            $password = array_shift($objects)["value"];

            $interaction->acknowledge()->done(function ()
            use ($interaction, $plan, $email, $password, $account, $session) {
                $account = $account->getNew(null, $email);

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
                    $interaction->sendFollowUpMessage(
                        $plan->persistentMessages->get($interaction, "0-logged_in"),
                        true
                    );
                } else {
                    $interaction->sendFollowUpMessage(MessageBuilder::new()->setContent(
                        $response
                    ), true);
                }
            });
        }
    }

    public static function change_username(DiscordPlan $plan,
                                           Interaction $interaction,
                                           mixed       $objects): void
    {
        $account = AccountMessageImplementationListener::getAccountSession($plan, $interaction->user->id);
        $account = $account->getSession();

        if ($account->isPositiveOutcome()) {
            $account = $account->getObject();
            $username = array_shift($objects->toArray())["value"];

            // Separator

            $interaction->acknowledge()->done(function () use ($interaction, $account, $username) {
                $interaction->sendFollowUpMessage(MessageBuilder::new()->setContent(
                    $account->getActions()->changeName($username)->getMessage()
                ), true);
            });
        } else {
            $plan->persistentMessages->send($interaction, "0-register_or_log_in", true);
        }
    }

    public static function new_email(DiscordPlan $plan,
                                     Interaction $interaction,
                                     mixed       $objects): void
    {
        $account = AccountMessageImplementationListener::getAccountSession($plan, $interaction->user->id);
        $account = $account->getSession();

        if ($account->isPositiveOutcome()) {
            $account = $account->getObject();
            $email = array_shift($objects->toArray())["value"];

            $interaction->acknowledge()->done(function () use ($interaction, $account, $email) {
                $interaction->sendFollowUpMessage(MessageBuilder::new()->setContent(
                    $account->getEmail()->requestVerification($email, true)->getMessage()
                ), true);
            });
        } else {
            $plan->persistentMessages->send($interaction, "0-register_or_log_in", true);
        }
    }

    public static function verify_email(DiscordPlan $plan,
                                        Interaction $interaction,
                                        mixed       $objects): void
    {
        $account = AccountMessageImplementationListener::getAccountSession($plan, $interaction->user->id);
        $account = $account->getSession();

        if ($account->isPositiveOutcome()) {
            $account = $account->getObject();
            $code = array_shift($objects->toArray())["value"];

            $interaction->acknowledge()->done(function () use ($interaction, $account, $code) {
                $interaction->sendFollowUpMessage(MessageBuilder::new()->setContent(
                    $account->getEmail()->completeVerification($code, true)->getMessage()
                ), true);
            });
        } else {
            $plan->persistentMessages->send($interaction, "0-register_or_log_in", true);
        }
    }

    public static function contact_form(DiscordPlan $plan,
                                        Interaction $interaction,
                                        mixed       $objects): void
    {
        $account = AccountMessageImplementationListener::getAccountSession($plan, $interaction->user->id);
        $account = $account->getSession();

        if ($account->isPositiveOutcome()) {
            $account = $account->getObject();
            $cacheKey = array(
                $interaction->user->id,
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
            $interaction->acknowledge()->done(function () use ($interaction, $response) {
                $interaction->sendFollowUpMessage(MessageBuilder::new()->setContent(
                    $response
                ), true);
            });
        } else {
            $plan->persistentMessages->send($interaction, "0-register_or_log_in", true);
        }
    }

    public static function forgot_password(DiscordPlan $plan,
                                           Interaction $interaction,
                                           mixed       $objects): void
    {
        $account = AccountMessageImplementationListener::getAccountSession($plan, $interaction->user->id);
        $account = $account->getSession();

        if ($account->isPositiveOutcome()) {
            $plan->persistentMessages->send($interaction, "0-logged_in", true);
        } else {
            $account = $account->getObject();
            $objects = $objects->toArray();
            $email = array_shift($objects)["value"];

            $interaction->acknowledge()->done(function () use ($interaction, $email, $account) {
                $account = $account->getNew(null, $email);

                if ($account->exists()) {
                    $response = $account->getPassword()->requestChange(true)->getMessage();
                } else {
                    $response = "Account with this email address does not exist.";
                }

                $interaction->sendFollowUpMessage(MessageBuilder::new()->setContent(
                    $response
                ), true);
            });
        }
    }

    public static function complete_password(DiscordPlan $plan,
                                             Interaction $interaction,
                                             mixed       $objects): void
    {
        $account = AccountMessageImplementationListener::getAccountSession($plan, $interaction->user->id);
        $account = $account->getSession();

        if ($account->isPositiveOutcome()) {
            $account = $account->getObject();
        } else {
            $account = new Account($plan->applicationID);
        }
        $objects = $objects->toArray();
        $code = array_shift($objects)["value"];
        $password1 = array_shift($objects)["value"];
        $password2 = array_shift($objects)["value"];

        $interaction->acknowledge()->done(function () use ($interaction, $account, $password1, $password2, $code) {
            if ($password1 == $password2) {
                $interaction->sendFollowUpMessage(MessageBuilder::new()->setContent(
                    $account->getPassword()->completeChange($code, $password1, true)->getMessage()
                ), true);
            } else {
                $interaction->sendFollowUpMessage(MessageBuilder::new()->setContent(
                    "Passwords do not match each other."
                ), true);
            }
        });
    }
}