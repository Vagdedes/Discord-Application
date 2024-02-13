<?php

use Discord\Builders\MessageBuilder;
use Discord\Parts\Interactions\Interaction;

class AccountModalImplementationListener
{

    public static function register(DiscordPlan $plan,
                                    Interaction $interaction,
                                    mixed       $objects): void
    {
        $account = AccountMessageCreationListener::findAccountFromSession($interaction, $plan);

        if ($account !== null) {
            $plan->persistentMessages->send($interaction, "0-logged_in", true);
        } else {
            $account = AccountMessageCreationListener::getAccountObject($interaction, $plan);
            $objects = $objects->toArray();
            $email = array_shift($objects)["value"];
            $username = array_shift($objects)["value"];

            $interaction->acknowledge()
                ->done(function () use ($interaction, $plan, $account, $email, $username) {
                    $accountRegistry = $account->getRegistry()->create(
                        $email,
                        null,
                        $username,
                        null,
                        null,
                        null
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
        $account = AccountMessageCreationListener::findAccountFromSession($interaction, $plan);

        if ($account !== null) {
            $plan->persistentMessages->send($interaction, "0-logged_in", true);
        } else {
            $account = AccountMessageCreationListener::getAccountObject($interaction, $plan);
            $objects = $objects->toArray();
            $email = array_shift($objects)["value"];

            $interaction->acknowledge()->done(function ()
            use ($interaction, $plan, $email, $account) {
                $account = $account->getNew(null, $email);

                if ($account->exists()) {
                    $result = $account->getActions()->logIn(null, "");

                    if ($result->isPositiveOutcome()) {
                        $response = null;
                    } else {
                        $response = $result->getMessage();
                        AccountMessageCreationListener::setAttemptedAccountSession($interaction, $plan, $account);
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

    public static function log_in_verification(DiscordPlan $plan,
                                               Interaction $interaction,
                                               mixed       $objects): void
    {
        $account = AccountMessageCreationListener::findAccountFromSession($interaction, $plan);

        if ($account !== null) {
            $plan->persistentMessages->send($interaction, "0-logged_in", true);
        } else {
            $objects = $objects->toArray();
            $code = array_shift($objects)["value"];

            $interaction->acknowledge()->done(function ()
            use ($interaction, $plan, $code, $account) {
                $account = AccountMessageCreationListener::getAttemptedAccountSession($interaction, $plan);

                if ($account->exists()) {
                    $result = $account->getActions()->logIn(null, $code);

                    if ($result->isPositiveOutcome()) {
                        $response = null;
                    } else {
                        $response = $result->getMessage();
                        AccountMessageCreationListener::setAttemptedAccountSession($interaction, $plan, $account);
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
        $account = AccountMessageCreationListener::findAccountFromSession($interaction, $plan);

        if ($account !== null) {
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
        $account = AccountMessageCreationListener::findAccountFromSession($interaction, $plan);

        if ($account !== null) {
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
        $account = AccountMessageCreationListener::findAccountFromSession($interaction, $plan);

        if ($account !== null) {
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
        $interaction->acknowledge()->done(function () use ($interaction, $objects, $plan) {
            $account = AccountMessageCreationListener::findAccountFromSession($interaction, $plan);

            if ($account !== null) {
                $cacheKey = array(
                    $interaction->user->id,
                    "contact-form"
                );
                if (has_memory_cooldown($cacheKey, null, false)) {
                    $response = "Please wait a few minutes before contacting us again.";
                } else {
                    $objects = $objects->toArray();
                    $content = $account->getEmail()->createTicket(
                        strip_tags(array_shift($objects)["value"]), // Subject
                        strip_tags(array_shift($objects)["value"]), // Info
                        null,
                        array(
                            "Discord-ID" => $interaction->user->id,
                            "Discord-Username" => $interaction->user->username
                        )
                    );

                    if (services_self_email($content[0], $content[1], $content[2]) === true) {
                        has_memory_cooldown($cacheKey, "5 minutes");
                        $response = "Thanks for taking the time to contact us.";
                    } else {
                        global $email_default_email_name;
                        $response = "An error occurred, please contact us at: " . $email_default_email_name;
                    }
                }
                $interaction->sendFollowUpMessage(MessageBuilder::new()->setContent(
                    $response
                ), true);
            } else {
                $interaction->sendFollowUpMessage(
                    $plan->persistentMessages->get($interaction, "0-register_or_log_in")
                );
            }
        });
    }

    public static function contact_form_offline(DiscordPlan $plan,
                                                Interaction $interaction,
                                                mixed       $objects): void
    {
        $interaction->acknowledge()->done(function () use ($interaction, $objects, $plan) {
            $cacheKey = array(
                $interaction->user->id,
                "contact-form"
            );

            if (has_memory_cooldown($cacheKey, null, false)) {
                $response = "Please wait a few minutes before contacting us again.";
            } else {
                $account = AccountMessageCreationListener::getAccountObject($interaction, $plan);
                $objects = $objects->toArray();
                $email = strip_tags(array_shift($objects)["value"]);
                $content = $account->getEmail()->createTicket(
                    strip_tags(array_shift($objects)["value"]), // Subject
                    strip_tags(array_shift($objects)["value"]), // Info
                    $email,
                    array(
                        "Discord-ID" => $interaction->user->id,
                        "Discord-Username" => $interaction->user->username
                    )
                );

                if (services_self_email($content[0], $content[1], $content[2]) === true) {
                    has_memory_cooldown($cacheKey, "5 minutes");
                    $response = "Thanks for taking the time to contact us.";
                } else {
                    global $email_default_email_name;
                    $response = "An error occurred, please contact us at: " . $email_default_email_name;
                }
            }
            $interaction->sendFollowUpMessage(MessageBuilder::new()->setContent(
                $response
            ), true);
        });
    }
}