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
        $account = AccountMessageCreationListener::findAccountFromSession($interaction, $plan);

        if ($account !== null) {
            $plan->persistentMessages->send($interaction, "0-logged_in", true);
        } else {
            $plan->persistentMessages->send($interaction, "0-register_or_log_in", true);
        }
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
            $plan->component->showModal($interaction, "0-log_in");
        }
    }

    public static function log_out(DiscordPlan    $plan,
                                   Interaction    $interaction,
                                   MessageBuilder $messageBuilder,
                                   mixed          $objects): void
    {
        $account = AccountMessageCreationListener::findAccountFromSession($interaction, $plan);

        if ($account === null) {
            $account = AccountMessageCreationListener::getAccountObject($interaction, $plan);
        }
        $plan->utilities->acknowledgeMessage(
            $interaction,
            MessageBuilder::new()->setContent(
                $account->getActions()->logOut()->getMessage()
            ),
            true
        );
    }

    public static function change_email(DiscordPlan    $plan,
                                        Interaction    $interaction,
                                        MessageBuilder $messageBuilder,
                                        mixed          $objects): void
    {
        $account = AccountMessageCreationListener::findAccountFromSession($interaction, $plan);

        if ($account !== null) {
            $plan->persistentMessages->send($interaction, "0-change_email", true);
        } else {
            $plan->component->showModal($interaction, "0-log_in");
        }
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

    public static function change_password(DiscordPlan    $plan,
                                           Interaction    $interaction,
                                           MessageBuilder $messageBuilder,
                                           mixed          $objects): void
    {
        $account = AccountMessageCreationListener::findAccountFromSession($interaction, $plan);

        if ($account !== null) {
            $plan->persistentMessages->send($interaction, "0-change_password", true);
        } else {
            $plan->component->showModal($interaction, "0-log_in");
        }
    }

    public static function request_password(DiscordPlan    $plan,
                                            Interaction    $interaction,
                                            MessageBuilder $messageBuilder,
                                            mixed          $objects): void
    {
        $account = AccountMessageCreationListener::findAccountFromSession($interaction, $plan);

        if ($account !== null) {
            $interaction->acknowledge()->done(function () use ($interaction, $account) {
                $interaction->sendFollowUpMessage(MessageBuilder::new()->setContent(
                    $account->getPassword()->requestChange(true)->getMessage()
                ), true);
            });
        } else {
            $plan->persistentMessages->send($interaction, "0-log_in", true);
        }
    }

    public static function complete_password(DiscordPlan    $plan,
                                             Interaction    $interaction,
                                             MessageBuilder $messageBuilder,
                                             mixed          $objects): void
    {
        $account = AccountMessageCreationListener::findAccountFromSession($interaction, $plan);

        if ($account !== null) {
            $plan->component->showModal($interaction, "0-complete_password");
        } else {
            $plan->persistentMessages->send($interaction, "0-log_in", true);
        }
    }

    public static function forgot_password(DiscordPlan    $plan,
                                           Interaction    $interaction,
                                           MessageBuilder $messageBuilder,
                                           mixed          $objects): void
    {
        $account = AccountMessageCreationListener::findAccountFromSession($interaction, $plan);

        if ($account !== null) {
            $plan->persistentMessages->send($interaction, "0-logged_in", true);
        } else {
            $plan->component->showModal($interaction, "0-forgot_password");
        }
    }

    public static function got_password_code(DiscordPlan    $plan,
                                             Interaction    $interaction,
                                             MessageBuilder $messageBuilder,
                                             mixed          $objects): void
    {
        $account = AccountMessageCreationListener::findAccountFromSession($interaction, $plan);

        if ($account !== null) {
            $plan->persistentMessages->send($interaction, "0-logged_in", true);
        } else {
            $plan->component->showModal($interaction, "0-complete_password");
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
        $account = AccountMessageCreationListener::findAccountFromSession($interaction, $plan);

        if ($account !== null) {
            $plan->utilities->acknowledgeMessage(
                $interaction,
                MessageBuilder::new()->setContent(
                    $account->getSettings()->toggle($objects[0]->getValue())->getMessage()
                ), true
            );
        } else {
            $plan->component->showModal($interaction, "0-log_in");
        }
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
                                $account->getAccounts()->add($selectedAccountID, $credential)->getMessage()
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
        $account = AccountMessageCreationListener::findAccountFromSession($interaction, $plan);

        if ($account !== null) {
            $selectedAccountID = $objects[0]->getValue();

            if (!is_numeric($selectedAccountID)) {
                $plan->utilities->acknowledgeMessage(
                    $interaction,
                    MessageBuilder::new()->setContent($objects[0]->getLabel()),
                    true
                );
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
                            $plan->utilities->acknowledgeMessage(
                                $interaction,
                                MessageBuilder::new()->setContent(
                                    $account->getAccounts()->remove($selectedAccountID, $accounts[0]->id, 1)->getMessage()
                                ), true
                            );
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
                                    MessageBuilder::new()->setContent(
                                        $account->getAccounts()->remove($selectedAccountID, $options[0]->getValue(), 1)->getMessage()
                                    ), true
                                );
                            }, $plan->bot->discord);
                            $messageBuilder->addComponent($select);
                            $plan->utilities->acknowledgeMessage($interaction, $messageBuilder, true);
                        }
                    } else {
                        $plan->utilities->acknowledgeMessage(
                            $interaction,
                            MessageBuilder::new()->setContent("No accounts found."),
                            true
                        );
                    }
                } else {
                    $plan->utilities->acknowledgeMessage(
                        $interaction,
                        MessageBuilder::new()->setContent("Account not found."),
                        true
                    );
                }
            }
        } else {
            $plan->component->showModal($interaction, "0-log_in");
        }
    }

    public static function toggle_settings_click(DiscordPlan    $plan,
                                                 Interaction    $interaction,
                                                 MessageBuilder $messageBuilder,
                                                 mixed          $objects): void
    {
        $account = AccountMessageCreationListener::findAccountFromSession($interaction, $plan);

        if ($account !== null) {
            $plan->utilities->acknowledgeMessage(
                $interaction,
                $plan->component->addSelection($interaction, MessageBuilder::new(), "0-toggle_settings"),
                true
            );
        } else {
            $plan->component->showModal($interaction, "0-log_in");
        }
    }

    public static function connect_account_click(DiscordPlan    $plan,
                                                 Interaction    $interaction,
                                                 MessageBuilder $messageBuilder,
                                                 mixed          $objects): void
    {
        $account = AccountMessageCreationListener::findAccountFromSession($interaction, $plan);

        if ($account !== null) {
            $plan->utilities->acknowledgeMessage(
                $interaction,
                $plan->component->addSelection($interaction, MessageBuilder::new(), "0-connect_accounts"),
                true
            );
        } else {
            $plan->component->showModal($interaction, "0-log_in");
        }
    }

    public static function disconnect_account_click(DiscordPlan    $plan,
                                                    Interaction    $interaction,
                                                    MessageBuilder $messageBuilder,
                                                    mixed          $objects): void
    {
        $account = AccountMessageCreationListener::findAccountFromSession($interaction, $plan);

        if ($account !== null) {
            $plan->utilities->acknowledgeMessage(
                $interaction,
                $plan->component->addSelection($interaction, MessageBuilder::new(), "0-disconnect_accounts"),
                true
            );
        } else {
            $plan->component->showModal($interaction, "0-log_in");
        }
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
            $plan->component->showModal($interaction, "0-log_in");
        }
    }

    public static function contact_form_offline(DiscordPlan    $plan,
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
}