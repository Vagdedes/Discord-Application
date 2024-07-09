<?php

use Discord\Builders\MessageBuilder;
use Discord\Parts\Embed\Embed;
use Discord\Parts\Interactions\Interaction;

class AccountModalImplementationListener
{

    public static function register(DiscordPlan $plan,
                                    Interaction $interaction,
                                    mixed       $objects): void
    {
        $plan->utilities->acknowledgeMessage(
            $interaction,
            function () use ($plan, $interaction, $objects) {
                $account = AccountMessageCreationListener::findAccountFromSession($interaction, $plan, true);

                if ($account !== null) {
                    return $plan->persistentMessages->get($interaction, "0-logged_in");
                } else {
                    $account = AccountMessageCreationListener::getAccountObject($interaction, $plan);
                    $objects = $objects->toArray();
                    $email = array_shift($objects)["value"];
                    $username = array_shift($objects)["value"];
                    $accountRegistry = $account->getRegistry()->create(
                        $email,
                        null,
                        $username,
                        null,
                        null,
                        null
                    );

                    if ($accountRegistry->isPositiveOutcome()) {
                        return $plan->persistentMessages->get($interaction, "0-logged_in");
                    } else {
                        return MessageBuilder::new()->setContent(
                            $accountRegistry->getMessage()
                        );
                    }
                }
            },
            true
        );
    }

    public static function log_in(DiscordPlan $plan,
                                  Interaction $interaction,
                                  mixed       $objects): void
    {
        $plan->utilities->acknowledgeMessage(
            $interaction,
            function () use ($plan, $interaction, $objects) {
                $account = AccountMessageCreationListener::findAccountFromSession($interaction, $plan, true);

                if ($account !== null) {
                    return $plan->persistentMessages->get($interaction, "0-logged_in");
                } else {
                    $account = AccountMessageCreationListener::getAccountObject($interaction, $plan);
                    $objects = $objects->toArray();
                    $email = array_shift($objects)["value"];
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
                        return $plan->persistentMessages->get($interaction, "0-logged_in");
                    } else {
                        return MessageBuilder::new()->setContent(
                            $response
                        );
                    }
                }
            },
            true
        );
    }

    public static function log_in_verification(DiscordPlan $plan,
                                               Interaction $interaction,
                                               mixed       $objects): void
    {
        $plan->utilities->acknowledgeMessage(
            $interaction,
            function () use ($plan, $interaction, $objects) {
                $account = AccountMessageCreationListener::findAccountFromSession($interaction, $plan, true);

                if ($account !== null) {
                    return $plan->persistentMessages->get($interaction, "0-logged_in");
                } else {
                    $objects = $objects->toArray();
                    $code = array_shift($objects)["value"];
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
                        return $plan->persistentMessages->get($interaction, "0-logged_in");
                    } else {
                        return MessageBuilder::new()->setContent(
                            $response
                        );
                    }
                }
            },
            true
        );
    }

    public static function change_username(DiscordPlan $plan,
                                           Interaction $interaction,
                                           mixed       $objects): void
    {
        $plan->utilities->acknowledgeMessage(
            $interaction,
            function () use ($plan, $interaction, $objects) {
                $account = AccountMessageCreationListener::findAccountFromSession($interaction, $plan, true);

                if ($account !== null) {
                    $username = array_shift($objects->toArray())["value"];
                    return MessageBuilder::new()->setContent(
                        $account->getActions()->changeName($username, true)->getMessage()
                    );
                } else {
                    return $plan->persistentMessages->get($interaction, "0-register_or_log_in");
                }
            },
            true
        );
    }

    public static function new_email(DiscordPlan $plan,
                                     Interaction $interaction,
                                     mixed       $objects): void
    {
        $plan->utilities->acknowledgeMessage(
            $interaction,
            function () use ($plan, $interaction, $objects) {
                $account = AccountMessageCreationListener::findAccountFromSession($interaction, $plan, true);

                if ($account !== null) {
                    $email = array_shift($objects->toArray())["value"];
                    return MessageBuilder::new()->setContent(
                        $account->getEmail()->requestVerification($email, true)->getMessage()
                    );
                } else {
                    return $plan->persistentMessages->get($interaction, "0-register_or_log_in");
                }
            },
            true
        );
    }

    public static function verify_email(DiscordPlan $plan,
                                        Interaction $interaction,
                                        mixed       $objects): void
    {
        $plan->utilities->acknowledgeMessage(
            $interaction,
            function () use ($plan, $interaction, $objects) {
                $account = AccountMessageCreationListener::findAccountFromSession($interaction, $plan, true);

                if ($account !== null) {
                    $code = array_shift($objects->toArray())["value"];
                    return MessageBuilder::new()->setContent(
                        $account->getEmail()->completeVerification($code, true)->getMessage()
                    );
                } else {
                    return $plan->persistentMessages->get($interaction, "0-register_or_log_in");
                }
            },
            true
        );
    }

    public static function contact_form(DiscordPlan $plan,
                                        Interaction $interaction,
                                        mixed       $objects): void
    {
        $plan->utilities->acknowledgeMessage(
            $interaction,
            function () use ($plan, $interaction, $objects) {
                $account = AccountMessageCreationListener::findAccountFromSession($interaction, $plan, true);

                if ($account !== null) {
                    $cacheKey = array(
                        $interaction->user->id,
                        "contact-form"
                    );
                    if (has_memory_cooldown($cacheKey, null, false)) {
                        $response = "Please wait a few minutes before contacting us again.";
                    } else {
                        $objects = $objects->toArray();
                        $subject = strip_tags(array_shift($objects)["value"]);
                        $roles = array();

                        foreach ($interaction->member->roles as $role) {
                            $roles[] = "'" . $role->name . "'";
                        }
                        $content = $account->getEmail()->createTicket(
                            $subject, // Subject
                            strip_tags(array_shift($objects)["value"]), // Info
                            null,
                            array(
                                "Discord-ID" => $interaction->user->id,
                                "Discord-Username" => $interaction->user->username,
                                "Discord-Roles" => implode(", ", $roles)
                            )
                        );

                        if (services_self_email($content[0], $content[1], $content[2]) === true) {
                            has_memory_cooldown($cacheKey, "5 minutes");
                            $response = "Thanks for taking the time to contact us.";
                            //self::sendEmailTicketEmbed($plan, $account->getDetail("name"), null, $subject);
                        } else {
                            global $email_default_email_name;
                            $response = "An error occurred, please contact us at: " . $email_default_email_name;
                        }
                    }
                    return MessageBuilder::new()->setContent(
                        $response
                    );
                } else {
                    return $plan->persistentMessages->get($interaction, "0-register_or_log_in");
                }
            },
            true
        );
    }

    public static function contact_form_offline(DiscordPlan $plan,
                                                Interaction $interaction,
                                                mixed       $objects): void
    {
        $plan->utilities->acknowledgeMessage(
            $interaction,
            function () use ($plan, $interaction, $objects) {
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
                    $subject = strip_tags(array_shift($objects)["value"]);
                    $roles = array();

                    foreach ($interaction->member->roles as $role) {
                        $roles[] = "'" . $role->name . "'";
                    }
                    $content = $account->getEmail()->createTicket(
                        $subject, // Subject
                        strip_tags(array_shift($objects)["value"]), // Info
                        $email,
                        array(
                            "Discord-ID" => $interaction->user->id,
                            "Discord-Username" => $interaction->user->username,
                            "Discord-Roles" => implode(", ", $roles)
                        )
                    );

                    if (services_self_email($content[0], $content[1], $content[2]) === true) {
                        has_memory_cooldown($cacheKey, "5 minutes");
                        $response = "Thanks for taking the time to contact us.";
                        //self::sendEmailTicketEmbed($plan, null, $email, $subject);
                    } else {
                        global $email_default_email_name;
                        $response = "An error occurred, please contact us at: " . $email_default_email_name;
                    }
                }
                return MessageBuilder::new()->setContent(
                    $response
                );
            },
            true
        );
    }

    private static function sendEmailTicketEmbed(DiscordPlan $plan,
                                                 ?string     $name, ?string $email, ?string $subject): void
    {
        $channel = $plan->bot->discord->getChannel("TO-DO");

        if ($channel !== null
            && $channel->allowText()
            && !empty($channel->threads->first())) {
            global $website_domain;

            foreach ($channel->threads as $thread) {
                if ($thread->id === "TO-DO") {
                    $message = MessageBuilder::new();
                    $embed = new Embed($plan->bot->discord);

                    if ($name !== null) {
                        $embed->addFieldValues(
                            "Name",
                            DiscordSyntax::LIGHT_CODE_BLOCK . $name . DiscordSyntax::LIGHT_CODE_BLOCK
                        );
                    } else {
                        $embed->addFieldValues(
                            "Email",
                            DiscordSyntax::LIGHT_CODE_BLOCK
                            . "xxxxx" . substr($email, strpos($email, "@"))
                            . DiscordSyntax::LIGHT_CODE_BLOCK
                        );
                    }
                    $embed->addFieldValues(
                        "Subject",
                        DiscordSyntax::LIGHT_CODE_BLOCK . $subject . DiscordSyntax::LIGHT_CODE_BLOCK
                    );
                    $embed->setTimestamp(time());
                    $message->addEmbed($embed);
                    $thread->sendMessage($message);
                    break;
                }
            }
        }
    }

}