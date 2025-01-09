<?php

use Discord\Builders\MessageBuilder;
use Discord\Parts\Embed\Embed;
use Discord\Parts\Interactions\Interaction;

class AccountModalImplementationListener
{

    public static function register(DiscordBot  $bot,
                                    Interaction $interaction,
                                    mixed       $objects): void
    {
        $bot->utilities->acknowledgeMessage(
            $interaction,
            $bot->utilities->functionWithException(
                function () use ($bot, $interaction, $objects) {
                    $account = AccountMessageCreationListener::findAccountFromSession($interaction);

                    if ($account !== null) {
                        return $bot->persistentMessages->get($interaction, "0-logged_in");
                    } else {
                        $account = AccountMessageCreationListener::getAccountObject($interaction);
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
                            return $bot->persistentMessages->get($interaction, "0-logged_in");
                        } else {
                            return MessageBuilder::new()->setContent(
                                $accountRegistry->getMessage()
                            );
                        }
                    }
                }
            ),
            true
        );
    }

    public static function log_in(DiscordBot  $bot,
                                  Interaction $interaction,
                                  mixed       $objects): void
    {
        $bot->utilities->acknowledgeMessage(
            $interaction,
            $bot->utilities->functionWithException(
                function () use ($bot, $interaction, $objects) {
                    $account = AccountMessageCreationListener::findAccountFromSession($interaction);

                    if ($account !== null) {
                        return $bot->persistentMessages->get($interaction, "0-logged_in");
                    } else {
                        $account = AccountMessageCreationListener::getAccountObject($interaction);
                        $objects = $objects->toArray();
                        $email = array_shift($objects)["value"];
                        $account = $account->transform(null, $email);

                        if ($account->exists()) {
                            $result = $account->getActions()->logIn(null, "");

                            if ($result->isPositiveOutcome()) {
                                $response = null;
                            } else {
                                $response = $result->getMessage();
                                AccountMessageCreationListener::setAttemptedAccountSession($interaction, $account);
                            }
                        } else {
                            $response = "Account with this email does not exist.";
                        }

                        // Separator

                        if ($response === null) {
                            return $bot->persistentMessages->get($interaction, "0-logged_in");
                        } else {
                            return MessageBuilder::new()->setContent(
                                $response
                            );
                        }
                    }
                }
            ),
            true
        );
    }

    public static function log_in_verification(DiscordBot  $bot,
                                               Interaction $interaction,
                                               mixed       $objects): void
    {
        $bot->utilities->acknowledgeMessage(
            $interaction,
            $bot->utilities->functionWithException(
                function () use ($bot, $interaction, $objects) {
                    $account = AccountMessageCreationListener::findAccountFromSession($interaction);


                    if ($account !== null) {
                        AccountMessageCreationListener::clearAttemptedAccountSession($interaction);
                        return $bot->persistentMessages->get($interaction, "0-logged_in");
                    } else {
                        $objects = $objects->toArray();
                        $code = array_shift($objects)["value"];
                        $account = AccountMessageCreationListener::getAttemptedAccountSession($interaction);

                        if ($account->exists()) {
                            $result = $account->getActions()->logIn(null, $code);

                            if ($result->isPositiveOutcome()) {
                                $response = null;
                                AccountMessageCreationListener::clearAttemptedAccountSession($interaction);
                            } else {
                                $response = $result->getMessage();
                                AccountMessageCreationListener::setAttemptedAccountSession($interaction, $account);
                            }
                        } else {
                            $response = "Account with this email does not exist.";
                            AccountMessageCreationListener::clearAttemptedAccountSession($interaction);
                        }

                        // Separator

                        if ($response === null) {
                            return $bot->persistentMessages->get($interaction, "0-logged_in");
                        } else {
                            return MessageBuilder::new()->setContent(
                                $response
                            );
                        }
                    }
                }
            ),
            true
        );
    }

    public static function change_username(DiscordBot  $bot,
                                           Interaction $interaction,
                                           mixed       $objects): void
    {
        $bot->utilities->acknowledgeMessage(
            $interaction,
            $bot->utilities->functionWithException(
                function () use ($bot, $interaction, $objects) {
                    $account = AccountMessageCreationListener::findAccountFromSession($interaction);

                    if ($account !== null) {
                        $username = array_shift($objects->toArray())["value"];
                        return MessageBuilder::new()->setContent(
                            $account->getActions()->changeName($username, true)->getMessage()
                        );
                    } else {
                        return $bot->persistentMessages->get($interaction, "0-register_or_log_in");
                    }
                }
            ),
            true
        );
    }

    public static function new_email(DiscordBot  $bot,
                                     Interaction $interaction,
                                     mixed       $objects): void
    {
        $bot->utilities->acknowledgeMessage(
            $interaction,
            $bot->utilities->functionWithException(
                function () use ($bot, $interaction, $objects) {
                    $account = AccountMessageCreationListener::findAccountFromSession($interaction);

                    if ($account !== null) {
                        $email = array_shift($objects->toArray())["value"];
                        return MessageBuilder::new()->setContent(
                            $account->getEmail()->requestVerification($email, true)->getMessage()
                        );
                    } else {
                        return $bot->persistentMessages->get($interaction, "0-register_or_log_in");
                    }
                }
            ),
            true
        );
    }

    public static function verify_email(DiscordBot  $bot,
                                        Interaction $interaction,
                                        mixed       $objects): void
    {
        $bot->utilities->acknowledgeMessage(
            $interaction,
            $bot->utilities->functionWithException(
                function () use ($bot, $interaction, $objects) {
                    $account = AccountMessageCreationListener::findAccountFromSession($interaction);

                    if ($account !== null) {
                        $code = clone $objects;
                        $code = array_shift($code->toArray())["value"];
                        return MessageBuilder::new()->setContent(
                            $account->getEmail()->completeVerification($code, true)->getMessage()
                        );
                    } else {
                        return $bot->persistentMessages->get($interaction, "0-register_or_log_in");
                    }
                }
            ),
            true
        );
    }

    public static function contact_form(DiscordBot  $bot,
                                        Interaction $interaction,
                                        mixed       $objects): void
    {
        $bot->utilities->acknowledgeMessage(
            $interaction,
            $bot->utilities->functionWithException(
                function () use ($bot, $interaction, $objects) {
                    $account = AccountMessageCreationListener::findAccountFromSession($interaction);

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
                                //self::sendEmailTicketEmbed($bot, $account->getDetail("name"), null, $subject);
                            } else {
                                global $email_default_email_name;
                                $response = "An error occurred, please contact us at: " . $email_default_email_name;
                            }
                        }
                        return MessageBuilder::new()->setContent(
                            $response
                        );
                    } else {
                        return $bot->persistentMessages->get($interaction, "0-register_or_log_in");
                    }
                }
            ),
            true
        );
    }

    public static function contact_form_offline(DiscordBot  $bot,
                                                Interaction $interaction,
                                                mixed       $objects): void
    {
        $bot->utilities->acknowledgeMessage(
            $interaction,
            $bot->utilities->functionWithException(
                function () use ($bot, $interaction, $objects) {
                    $cacheKey = array(
                        $interaction->user->id,
                        "contact-form"
                    );

                    if (has_memory_cooldown($cacheKey, null, false)) {
                        $response = "Please wait a few minutes before contacting us again.";
                    } else {
                        $account = AccountMessageCreationListener::getAccountObject($interaction);
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
                            //self::sendEmailTicketEmbed($bot, null, $email, $subject);
                        } else {
                            global $email_default_email_name;
                            $response = "An error occurred, please contact us at: " . $email_default_email_name;
                        }
                    }
                    return MessageBuilder::new()->setContent(
                        $response
                    );
                }
            ),
            true
        );
    }

    private static function sendEmailTicketEmbed(DiscordBot $bot,
                                                 ?string    $name, ?string $email, ?string $subject): void
    {
        $channel = $bot->discord->getChannel("TO-DO");

        if ($channel !== null
            && $bot->utilities->allowText($channel)
            && !empty($channel->threads->first())) {
            foreach ($channel->threads as $thread) {
                if ($thread->id === "TO-DO") {
                    $message = MessageBuilder::new();
                    $embed = new Embed($bot->discord);

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