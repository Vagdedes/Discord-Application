<?php

use Discord\Builders\Components\Option;
use Discord\Builders\Components\SelectMenu;
use Discord\Builders\Components\TextInput;
use Discord\Builders\MessageBuilder;
use Discord\Helpers\Collection;
use Discord\Parts\Interactions\Interaction;

class AccountMessageImplementationListener
{

    public static function my_account(DiscordPlan    $plan,
                                      Interaction    $interaction,
                                      MessageBuilder $messageBuilder,
                                      mixed          $objects): void
    {
        $plan->utilities->acknowledgeMessage(
            $interaction,
            function () use ($interaction, $plan) {
                $account = AccountMessageCreationListener::findAccountFromSession($interaction, $plan);

                if ($account !== null) {
                    return $plan->persistentMessages->get($interaction, "0-logged_in");
                } else {
                    return $plan->persistentMessages->get($interaction, "0-register_or_log_in");
                }
            },
            true
        );
    }

    public static function register(DiscordPlan    $plan,
                                    Interaction    $interaction,
                                    MessageBuilder $messageBuilder,
                                    mixed          $objects): void
    {
        $account = AccountMessageCreationListener::findAccountFromSession($interaction, $plan);

        if ($account !== null) {
            $plan->persistentMessages->send($interaction, "0-logged_in", true);
        } else {
            $plan->component->showModal($interaction, "0-register");
        }
    }

    public static function log_in(DiscordPlan    $plan,
                                  Interaction    $interaction,
                                  MessageBuilder $messageBuilder,
                                  mixed          $objects): void
    {
        $account = AccountMessageCreationListener::findAccountFromSession($interaction, $plan);

        if ($account !== null) {
            $plan->persistentMessages->send($interaction, "0-logged_in", true);
        } else {
            $account = AccountMessageCreationListener::getAttemptedAccountSession($interaction, $plan);

            if ($account !== null
                && $account->getTwoFactorAuthentication()->isPending()) {
                $plan->component->showModal($interaction, "0-log_in_verification");
            } else {
                $plan->component->showModal($interaction, "0-log_in");
            }
        }
    }

    public static function log_out(DiscordPlan    $plan,
                                   Interaction    $interaction,
                                   MessageBuilder $messageBuilder,
                                   mixed          $objects): void
    {
        $plan->utilities->acknowledgeMessage(
            $interaction,
            function () use ($interaction, $plan) {
                $account = AccountMessageCreationListener::findAccountFromSession($interaction, $plan);

                if ($account === null) {
                    $account = AccountMessageCreationListener::getAccountObject($interaction, $plan);
                }
                AccountMessageCreationListener::clearAttemptedAccountSession($interaction, $plan);
                return MessageBuilder::new()->setContent(
                    $account->getActions()->logOut()->getMessage()
                );
            },
            true
        );
    }

    public static function manage_email(DiscordPlan    $plan,
                                        Interaction    $interaction,
                                        MessageBuilder $messageBuilder,
                                        mixed          $objects): void
    {
        $plan->utilities->acknowledgeMessage(
            $interaction,
            function () use ($interaction, $plan) {
                $account = AccountMessageCreationListener::findAccountFromSession($interaction, $plan);

                if ($account !== null) {
                    return $plan->persistentMessages->get($interaction, "0-change_email", true);
                } else {
                    return MessageBuilder::new()->setContent("You are not logged in.");
                }
            },
            true
        );
    }

    public static function new_email(DiscordPlan    $plan,
                                     Interaction    $interaction,
                                     MessageBuilder $messageBuilder,
                                     mixed          $objects): void
    {
        $account = AccountMessageCreationListener::findAccountFromSession($interaction, $plan);

        if ($account !== null) {
            $plan->component->showModal($interaction, "0-new_email");
        } else {
            $plan->component->showModal($interaction, "0-log_in");
        }
    }

    public static function verify_email(DiscordPlan    $plan,
                                        Interaction    $interaction,
                                        MessageBuilder $messageBuilder,
                                        mixed          $objects): void
    {
        $account = AccountMessageCreationListener::findAccountFromSession($interaction, $plan);

        if ($account !== null) {
            $plan->component->showModal($interaction, "0-verify_email");
        } else {
            $plan->component->showModal($interaction, "0-log_in");
        }
    }

    public static function change_username(DiscordPlan    $plan,
                                           Interaction    $interaction,
                                           MessageBuilder $messageBuilder,
                                           mixed          $objects): void
    {
        $account = AccountMessageCreationListener::findAccountFromSession($interaction, $plan);

        if ($account !== null) {
            $plan->component->showModal($interaction, "0-change_username");
        } else {
            $plan->component->showModal($interaction, "0-log_in");
        }
    }

    public static function toggle_settings(DiscordPlan    $plan,
                                           Interaction    $interaction,
                                           MessageBuilder $messageBuilder,
                                           mixed          $objects): void
    {
        $plan->utilities->acknowledgeMessage(
            $interaction,
            function () use ($interaction, $plan, $objects) {
                $account = AccountMessageCreationListener::findAccountFromSession($interaction, $plan);

                if ($account !== null) {
                    return MessageBuilder::new()->setContent(
                        $account->getSettings()->toggle($objects[0]->getValue())->getMessage()
                    );
                } else {
                    return MessageBuilder::new()->setContent("You are not logged in.");
                }
            }, true

        );
    }

    public static function connect_account(DiscordPlan    $plan,
                                           Interaction    $interaction,
                                           MessageBuilder $messageBuilder,
                                           mixed          $objects): void
    {
        $account = AccountMessageCreationListener::findAccountFromSession($interaction, $plan);

        if ($account !== null) {
            $selectedAccountID = $objects[0]->getValue();
            $selectedAccountName = $account->getAccounts()->getAvailable(array("name"), $selectedAccountID);

            if (!empty($selectedAccountName)) {
                $selectedAccountName = $selectedAccountName[0]->name;
                $plan->component->createModal(
                    $interaction,
                    "Connect Account",
                    array(
                        TextInput::new($selectedAccountName, TextInput::STYLE_SHORT)
                            ->setMinLength(1)->setMaxLength(384)
                            ->setPlaceholder("Insert the credential here.")
                    ),
                    null,
                    function (Interaction $interaction, Collection $components)
                    use ($plan, $account, $selectedAccountID) {
                        $components = $components->toArray();
                        $credential = array_shift($components)["value"];
                        $plan->utilities->acknowledgeMessage(
                            $interaction,
                            MessageBuilder::new()->setContent(
                                $account->getAccounts()->add($selectedAccountID, $credential, 0, true)->getMessage()
                            ), true
                        );
                    },
                );
            } else {
                $plan->utilities->acknowledgeMessage(
                    $interaction,
                    MessageBuilder::new()->setContent("Account not found."),
                    true
                );
            }
        } else {
            $plan->component->showModal($interaction, "0-log_in");
        }
    }

    public static function disconnect_account(DiscordPlan    $plan,
                                              Interaction    $interaction,
                                              MessageBuilder $messageBuilder,
                                              mixed          $objects): void
    {
        $plan->utilities->acknowledgeMessage(
            $interaction,
            function () use ($interaction, $plan, $objects) {
                $account = AccountMessageCreationListener::findAccountFromSession($interaction, $plan);

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
                                    $select->setListener(function (Interaction $interaction, Collection $options)
                                    use ($plan, $account, $selectedAccountID) {
                                        $plan->utilities->acknowledgeMessage(
                                            $interaction,
                                            function () use ($account, $selectedAccountID, $options) {
                                                return MessageBuilder::new()->setContent(
                                                    $account->getAccounts()->remove($selectedAccountID, $options[0]->getValue(), 1)->getMessage()
                                                );
                                            },
                                            true
                                        );
                                    }, $plan->bot->discord);
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
            },
            true
        );
    }

    public static function toggle_settings_click(DiscordPlan    $plan,
                                                 Interaction    $interaction,
                                                 MessageBuilder $messageBuilder,
                                                 mixed          $objects): void
    {
        $plan->utilities->acknowledgeMessage(
            $interaction,
            function () use ($interaction, $plan) {
                $account = AccountMessageCreationListener::findAccountFromSession($interaction, $plan);

                if ($account !== null) {
                    $messageBuilder = $plan->component->addSelection(
                        $interaction,
                        MessageBuilder::new(),
                        "0-toggle_settings"
                    );
                    return $plan->component->addButtons($interaction, $messageBuilder, "0-change_username");
                } else {
                    return MessageBuilder::new()->setContent("You are not logged in.");
                }
            },
            true
        );
    }

    public static function connect_account_click(DiscordPlan    $plan,
                                                 Interaction    $interaction,
                                                 MessageBuilder $messageBuilder,
                                                 mixed          $objects): void
    {
        $plan->utilities->acknowledgeMessage(
            $interaction,
            function () use ($interaction, $plan) {
                $account = AccountMessageCreationListener::findAccountFromSession($interaction, $plan);

                if ($account !== null) {
                    return $plan->component->addSelection($interaction, MessageBuilder::new(), "0-connect_accounts");
                } else {
                    return MessageBuilder::new()->setContent("You are not logged in.");
                }
            },
            true
        );
    }

    public static function disconnect_account_click(DiscordPlan    $plan,
                                                    Interaction    $interaction,
                                                    MessageBuilder $messageBuilder,
                                                    mixed          $objects): void
    {
        $plan->utilities->acknowledgeMessage(
            $interaction,
            function () use ($interaction, $plan) {
                $account = AccountMessageCreationListener::findAccountFromSession($interaction, $plan);

                if ($account !== null) {
                    return $plan->component->addSelection($interaction, MessageBuilder::new(), "0-disconnect_accounts");
                } else {
                    return MessageBuilder::new()->setContent("You are not logged in.");
                }
            },
            true
        );
    }

    public static function contact_form(DiscordPlan    $plan,
                                        Interaction    $interaction,
                                        MessageBuilder $messageBuilder,
                                        mixed          $objects): void
    {
        $account = AccountMessageCreationListener::findAccountFromSession($interaction, $plan);

        if ($account !== null) {
            $plan->component->showModal($interaction, "0-contact_form");
        } else {
            $plan->component->showModal($interaction, "0-contact_form_offline");
        }
    }

    public static function download_plugins(DiscordPlan    $plan,
                                            Interaction    $interaction,
                                            MessageBuilder $messageBuilder,
                                            mixed          $objects): void
    {
        $plan->persistentMessages->send($interaction, "0-download_plugins", true);
    }
}