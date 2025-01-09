<?php

use Discord\Builders\Components\Option;
use Discord\Builders\Components\SelectMenu;
use Discord\Builders\Components\TextInput;
use Discord\Builders\MessageBuilder;
use Discord\Helpers\Collection;
use Discord\Parts\Interactions\Interaction;

class AccountMessageImplementationListener
{

    public static function my_account(DiscordBot     $bot,
                                      Interaction    $interaction,
                                      MessageBuilder $messageBuilder,
                                      mixed          $objects): void
    {
        $bot->utilities->acknowledgeMessage(
            $interaction,
            $bot->utilities->functionWithException(
                function () use ($interaction, $bot) {
                    $account = AccountMessageCreationListener::findAccountFromSession($interaction);

                    if ($account !== null) {
                        return $bot->persistentMessages->get($interaction, "0-logged_in");
                    } else {
                        return $bot->persistentMessages->get($interaction, "0-register_or_log_in");
                    }
                }
            ),
            true
        );
    }

    public static function register(DiscordBot     $bot,
                                    Interaction    $interaction,
                                    MessageBuilder $messageBuilder,
                                    mixed          $objects): void
    {
        $account = AccountMessageCreationListener::findAccountFromSession($interaction);

        if ($account !== null) {
            $bot->persistentMessages->send($interaction, "0-logged_in", true);
        } else {
            $bot->component->showModal($interaction, "0-register");
        }
    }

    public static function log_in(DiscordBot     $bot,
                                  Interaction    $interaction,
                                  MessageBuilder $messageBuilder,
                                  mixed          $objects): void
    {
        $account = AccountMessageCreationListener::findAccountFromSession($interaction);

        if ($account !== null) {
            $bot->persistentMessages->send($interaction, "0-logged_in", true);
        } else {
            $account = AccountMessageCreationListener::getAttemptedAccountSession($interaction);

            if ($account !== null
                && $account->getTwoFactorAuthentication()->isPending()) {
                $bot->component->showModal($interaction, "0-log_in_verification");
            } else {
                $bot->component->showModal($interaction, "0-log_in");
            }
        }
    }

    public static function log_out(DiscordBot     $bot,
                                   Interaction    $interaction,
                                   MessageBuilder $messageBuilder,
                                   mixed          $objects): void
    {
        $bot->utilities->acknowledgeMessage(
            $interaction,
            $bot->utilities->functionWithException(
                function () use ($interaction, $bot) {
                    $account = AccountMessageCreationListener::findAccountFromSession($interaction);

                    if ($account === null) {
                        $account = AccountMessageCreationListener::getAccountObject($interaction);
                    }
                    AccountMessageCreationListener::clearAttemptedAccountSession($interaction);
                    return MessageBuilder::new()->setContent(
                        $account->getActions()->logOut()->getMessage()
                    );
                }
            ),
            true
        );
    }

    public static function manage_email(DiscordBot     $bot,
                                        Interaction    $interaction,
                                        MessageBuilder $messageBuilder,
                                        mixed          $objects): void
    {
        $bot->utilities->acknowledgeMessage(
            $interaction,
            $bot->utilities->functionWithException(
                function () use ($interaction, $bot) {
                    $account = AccountMessageCreationListener::findAccountFromSession($interaction);

                    if ($account !== null) {
                        return $bot->persistentMessages->get($interaction, "0-change_email", true);
                    } else {
                        return MessageBuilder::new()->setContent("You are not logged in.");
                    }
                }
            ),
            true
        );
    }

    public static function new_email(DiscordBot     $bot,
                                     Interaction    $interaction,
                                     MessageBuilder $messageBuilder,
                                     mixed          $objects): void
    {
        $account = AccountMessageCreationListener::findAccountFromSession($interaction);

        if ($account !== null) {
            $bot->component->showModal($interaction, "0-new_email");
        } else {
            $bot->component->showModal($interaction, "0-log_in");
        }
    }

    public static function verify_email(DiscordBot     $bot,
                                        Interaction    $interaction,
                                        MessageBuilder $messageBuilder,
                                        mixed          $objects): void
    {
        $account = AccountMessageCreationListener::findAccountFromSession($interaction);

        if ($account !== null) {
            $bot->component->showModal($interaction, "0-verify_email");
        } else {
            $bot->component->showModal($interaction, "0-log_in");
        }
    }

    public static function change_username(DiscordBot     $bot,
                                           Interaction    $interaction,
                                           MessageBuilder $messageBuilder,
                                           mixed          $objects): void
    {
        $account = AccountMessageCreationListener::findAccountFromSession($interaction);

        if ($account !== null) {
            $bot->component->showModal($interaction, "0-change_username");
        } else {
            $bot->component->showModal($interaction, "0-log_in");
        }
    }

    public static function toggle_settings(DiscordBot     $bot,
                                           Interaction    $interaction,
                                           MessageBuilder $messageBuilder,
                                           mixed          $objects): void
    {
        $bot->utilities->acknowledgeMessage(
            $interaction,
            $bot->utilities->functionWithException(
                function () use ($interaction, $bot, $objects) {
                    $account = AccountMessageCreationListener::findAccountFromSession($interaction);

                    if ($account !== null) {
                        return MessageBuilder::new()->setContent(
                            $account->getSettings()->toggle($objects[0]->getValue())->getMessage()
                        );
                    } else {
                        return MessageBuilder::new()->setContent("You are not logged in.");
                    }
                }
            ), true
        );
    }

    public static function connect_account(DiscordBot     $bot,
                                           Interaction    $interaction,
                                           MessageBuilder $messageBuilder,
                                           mixed          $objects): void
    {
        $account = AccountMessageCreationListener::findAccountFromSession($interaction);

        if ($account !== null) {
            $selectedAccountID = $objects[0]->getValue();
            $selectedAccountName = $account->getAccounts()->getAvailable(array("name"), $selectedAccountID);

            if (!empty($selectedAccountName)) {
                $selectedAccountName = $selectedAccountName[0]->name;
                $bot->component->createModal(
                    $interaction,
                    "Connect Account",
                    array(
                        TextInput::new($selectedAccountName, TextInput::STYLE_SHORT)
                            ->setMinLength(1)->setMaxLength(384)
                            ->setPlaceholder("Insert the credential here.")
                    ),
                    null,
                    $bot->utilities->functionWithException(
                        function (Interaction $interaction, Collection $components)
                        use ($bot, $account, $selectedAccountID) {
                            $components = $components->toArray();
                            $credential = array_shift($components)["value"];
                            $bot->utilities->acknowledgeMessage(
                                $interaction,
                                MessageBuilder::new()->setContent(
                                    $account->getAccounts()->add($selectedAccountID, $credential, 0, true)->getMessage()
                                ), true
                            );
                        }
                    ),
                );
            } else {
                $bot->utilities->acknowledgeMessage(
                    $interaction,
                    MessageBuilder::new()->setContent("Account not found."),
                    true
                );
            }
        } else {
            $bot->component->showModal($interaction, "0-log_in");
        }
    }

    public static function disconnect_account(DiscordBot     $bot,
                                              Interaction    $interaction,
                                              MessageBuilder $messageBuilder,
                                              mixed          $objects): void
    {
        $bot->utilities->acknowledgeMessage(
            $interaction,
            $bot->utilities->functionWithException(
                function () use ($interaction, $bot, $objects) {
                    $account = AccountMessageCreationListener::findAccountFromSession($interaction);

                    if ($account !== null) {
                        $selectedAccountID = $objects[0]->getValue();

                        if (!is_numeric($selectedAccountID)) {
                            return MessageBuilder::new()->setContent($objects[0]->getLabel());
                        } else {
                            $selectedAccountName = $account->getAccounts()->getAvailable(array("name"), $selectedAccountID);

                            if (!empty($selectedAccountName)) {
                                $accounts = $account->getAccounts()->getAdded(
                                    $selectedAccountID,
                                    DiscordInheritedLimits::MAX_CHOICES_PER_SELECTION,
                                    true
                                );

                                if (!empty($accounts)) {
                                    if (sizeof($accounts) === 1) {
                                        return $account->getAccounts()->remove($selectedAccountID, $accounts[0]->id, 1)->getMessage();
                                    } else {
                                        $selectedAccountName = $selectedAccountName[0]->name;
                                        $messageBuilder = MessageBuilder::new();
                                        $messageBuilder->setContent("Available **" . $selectedAccountName . "** Accounts");
                                        $select = SelectMenu::new()->setMinValues(1)->setMinValues(1);

                                        foreach ($accounts as $row) {
                                            $option = Option::new(substr($row->credential, 0, 100), $row->id);
                                            $select->addOption($option);
                                        }
                                        $select->setListener($bot->utilities->functionWithException(
                                            function (Interaction $interaction, Collection $options)
                                            use ($bot, $account, $selectedAccountID) {
                                                $bot->utilities->acknowledgeMessage(
                                                    $interaction,
                                                    $bot->utilities->functionWithException(
                                                        function () use ($account, $selectedAccountID, $options) {
                                                            return MessageBuilder::new()->setContent(
                                                                $account->getAccounts()->remove($selectedAccountID, $options[0]->getValue(), 1)->getMessage()
                                                            );
                                                        }
                                                    ),
                                                    true
                                                );
                                            }
                                        ), $bot->discord);
                                        $messageBuilder->addComponent($select);
                                        return $messageBuilder;
                                    }
                                } else {
                                    return MessageBuilder::new()->setContent("No accounts found.");
                                }
                            } else {
                                return MessageBuilder::new()->setContent("Account not found.");
                            }
                        }
                    } else {
                        return MessageBuilder::new()->setContent("You are not logged in.");
                    }
                }
            ),
            true
        );
    }

    public static function toggle_settings_click(DiscordBot     $bot,
                                                 Interaction    $interaction,
                                                 MessageBuilder $messageBuilder,
                                                 mixed          $objects): void
    {
        $bot->utilities->acknowledgeMessage(
            $interaction,
            $bot->utilities->functionWithException(
                function () use ($interaction, $bot) {
                    $account = AccountMessageCreationListener::findAccountFromSession($interaction);

                    if ($account !== null) {
                        $messageBuilder = $bot->component->addSelection(
                            $interaction,
                            MessageBuilder::new(),
                            "0-toggle_settings"
                        );
                        return $bot->component->addButtons($interaction, $messageBuilder, "0-change_username");
                    } else {
                        return MessageBuilder::new()->setContent("You are not logged in.");
                    }
                }
            ),
            true
        );
    }

    public static function connect_account_click(DiscordBot     $bot,
                                                 Interaction    $interaction,
                                                 MessageBuilder $messageBuilder,
                                                 mixed          $objects): void
    {
        $bot->utilities->acknowledgeMessage(
            $interaction,
            $bot->utilities->functionWithException(
                function () use ($interaction, $bot) {
                    $account = AccountMessageCreationListener::findAccountFromSession($interaction);

                    if ($account !== null) {
                        return $bot->component->addSelection($interaction, MessageBuilder::new(), "0-connect_accounts");
                    } else {
                        return MessageBuilder::new()->setContent("You are not logged in.");
                    }
                }
            ),
            true
        );
    }

    public static function disconnect_account_click(DiscordBot     $bot,
                                                    Interaction    $interaction,
                                                    MessageBuilder $messageBuilder,
                                                    mixed          $objects): void
    {
        $bot->utilities->acknowledgeMessage(
            $interaction,
            $bot->utilities->functionWithException(
                function () use ($interaction, $bot) {
                    $account = AccountMessageCreationListener::findAccountFromSession($interaction);

                    if ($account !== null) {
                        return $bot->component->addSelection($interaction, MessageBuilder::new(), "0-disconnect_accounts");
                    } else {
                        return MessageBuilder::new()->setContent("You are not logged in.");
                    }
                }
            ),
            true
        );
    }

    public static function contact_form(DiscordBot     $bot,
                                        Interaction    $interaction,
                                        MessageBuilder $messageBuilder,
                                        mixed          $objects): void
    {
        $account = AccountMessageCreationListener::findAccountFromSession($interaction);

        if ($account !== null) {
            $bot->component->showModal($interaction, "0-contact_form");
        } else {
            $bot->component->showModal($interaction, "0-contact_form_offline");
        }
    }

}