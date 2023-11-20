<?php

use Discord\Builders\Components\Option;
use Discord\Builders\Components\SelectMenu;
use Discord\Builders\Components\TextInput;
use Discord\Builders\MessageBuilder;
use Discord\Helpers\Collection;
use Discord\Parts\Embed\Embed;
use Discord\Parts\Interactions\Interaction;

class AccountMessageImplementationListener
{

    public const
        IDEALISTIC_NAME = "Idealistic AI",
        IDEALISTIC_LOGO = "https://vagdedes.com/.images/idealistic/logo.png";

    public static function getAccountSession(DiscordPlan $plan, int|string $userID): object
    {
        $account = new Account($plan->applicationID);
        $session = $account->getSession();
        $session->setCustomKey("discord", $userID);
        return $session;
    }

    public static function my_account(DiscordPlan    $plan,
                                      Interaction    $interaction,
                                      MessageBuilder $messageBuilder,
                                      mixed          $objects): void
    {
        $account = self::getAccountSession($plan, $interaction->user->id);
        $account = $account->getSession();

        if ($account->isPositiveOutcome()) {
            $plan->controlledMessages->send($interaction, "0-logged_in", true);
        } else {
            $plan->controlledMessages->send($interaction, "0-register_or_log_in", true);
        }
    }

    public static function register(DiscordPlan    $plan,
                                    Interaction    $interaction,
                                    MessageBuilder $messageBuilder,
                                    mixed          $objects): void
    {
        $account = self::getAccountSession($plan, $interaction->user->id);
        $account = $account->getSession();

        if ($account->isPositiveOutcome()) {
            $plan->controlledMessages->send($interaction, "0-logged_in", true);
        } else {
            $plan->component->showModal($interaction, "0-register");
        }
    }

    public static function log_in(DiscordPlan    $plan,
                                  Interaction    $interaction,
                                  MessageBuilder $messageBuilder,
                                  mixed          $objects): void
    {
        $account = self::getAccountSession($plan, $interaction->user->id);
        $account = $account->getSession();

        if ($account->isPositiveOutcome()) {
            $plan->controlledMessages->send($interaction, "0-logged_in", true);
        } else {
            $plan->component->showModal($interaction, "0-log_in");
        }
    }

    public static function log_out(DiscordPlan    $plan,
                                   Interaction    $interaction,
                                   MessageBuilder $messageBuilder,
                                   mixed          $objects): void
    {
        $account = self::getAccountSession($plan, $interaction->user->id);
        $plan->utilities->acknowledgeMessage(
            $interaction,
            MessageBuilder::new()->setContent(
                $account->getSession()->getObject()->getActions()->logOut($account)->getMessage()
            ),
            true
        );
    }

    public static function change_email(DiscordPlan    $plan,
                                        Interaction    $interaction,
                                        MessageBuilder $messageBuilder,
                                        mixed          $objects): void
    {
        $account = self::getAccountSession($plan, $interaction->user->id);
        $account = $account->getSession();

        if ($account->isPositiveOutcome()) {
            $plan->controlledMessages->send($interaction, "0-change_email", true);
        } else {
            $plan->component->showModal($interaction, "0-log_in");
        }
    }

    public static function new_email(DiscordPlan    $plan,
                                     Interaction    $interaction,
                                     MessageBuilder $messageBuilder,
                                     mixed          $objects): void
    {
        $account = self::getAccountSession($plan, $interaction->user->id);
        $account = $account->getSession();

        if ($account->isPositiveOutcome()) {
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
        $account = self::getAccountSession($plan, $interaction->user->id);
        $account = $account->getSession();

        if ($account->isPositiveOutcome()) {
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
        $account = self::getAccountSession($plan, $interaction->user->id);
        $account = $account->getSession();

        if ($account->isPositiveOutcome()) {
            $plan->controlledMessages->send($interaction, "0-change_password", true);
        } else {
            $plan->component->showModal($interaction, "0-log_in");
        }
    }

    public static function request_password(DiscordPlan    $plan,
                                            Interaction    $interaction,
                                            MessageBuilder $messageBuilder,
                                            mixed          $objects): void
    {
        $account = self::getAccountSession($plan, $interaction->user->id);
        $account = $account->getSession();

        if ($account->isPositiveOutcome()) {
            $account = $account->getObject();

            $interaction->acknowledge()->done(function () use ($interaction, $account) {
                $interaction->sendFollowUpMessage(MessageBuilder::new()->setContent(
                    $account->getPassword()->requestChange()->getMessage()
                ), true);
            });
        } else {
            $plan->controlledMessages->send($interaction, "0-log_in", true);
        }
    }

    public static function complete_password(DiscordPlan    $plan,
                                             Interaction    $interaction,
                                             MessageBuilder $messageBuilder,
                                             mixed          $objects): void
    {
        $account = self::getAccountSession($plan, $interaction->user->id);
        $account = $account->getSession();

        if ($account->isPositiveOutcome()) {
            $plan->component->showModal($interaction, "0-complete_password");
        } else {
            $plan->controlledMessages->send($interaction, "0-log_in", true);
        }
    }

    public static function forgot_password(DiscordPlan    $plan,
                                           Interaction    $interaction,
                                           MessageBuilder $messageBuilder,
                                           mixed          $objects): void
    {
        $account = self::getAccountSession($plan, $interaction->user->id);
        $account = $account->getSession();

        if ($account->isPositiveOutcome()) {
            $plan->controlledMessages->send($interaction, "0-logged_in", true);
        } else {
            $plan->component->showModal($interaction, "0-forgot_password");
        }
    }

    public static function got_password_code(DiscordPlan    $plan,
                                           Interaction    $interaction,
                                           MessageBuilder $messageBuilder,
                                           mixed          $objects): void
    {
        $account = self::getAccountSession($plan, $interaction->user->id);
        $account = $account->getSession();

        if ($account->isPositiveOutcome()) {
            $plan->controlledMessages->send($interaction, "0-logged_in", true);
        } else {
            $plan->component->showModal($interaction, "0-complete_password");
        }
    }

    public static function change_username(DiscordPlan    $plan,
                                           Interaction    $interaction,
                                           MessageBuilder $messageBuilder,
                                           mixed          $objects): void
    {
        $account = self::getAccountSession($plan, $interaction->user->id);
        $account = $account->getSession();

        if ($account->isPositiveOutcome()) {
            $plan->component->showModal($interaction, "0-change_username");
        } else {
            $plan->component->showModal($interaction, "0-log_in");
        }
    }

    public static function view_history(DiscordPlan    $plan,
                                        Interaction    $interaction,
                                        MessageBuilder $messageBuilder,
                                        mixed          $objects): void
    {
        $account = self::getAccountSession($plan, $interaction->user->id);
        $account = $account->getSession();

        if ($account->isPositiveOutcome()) {
            $account = $account->getObject();
            $history = $account->getHistory()->get(
                array("action_id", "creation_date"),
                100
            );

            if ($history->isPositiveOutcome()) {
                $history = $history->getObject();
                $size = sizeof($history);

                if ($size > 0) {
                    $limit = DiscordInheritedLimits::MAX_FIELDS_PER_EMBED;
                    $messageBuilder = MessageBuilder::new();

                    for ($i = 0; $i < ceil($size / $limit); $i++) {
                        $counter = $i * $limit;
                        $max = min($counter + $limit, $size);
                        $divisor = 0;
                        $embed = new Embed($plan->discord);
                        $embed->setTitle("Account History #" . ($i + 1));
                        $embed->setDescription(
                            "Here is your " . ($i !== 0 ? "next " : "") . ($max - $counter) . " past account actions for your convenience."
                        );

                        for ($x = $counter; $x < $max; $x++) {
                            $row = $history[$x];
                            $time = time() - strtotime($row->creation_date);
                            $embed->addFieldValues(
                                "__" . ($x + 1) . "__ " . str_replace("_", "-", $row->action_id),
                                "```" . get_full_date(get_past_date($time . " seconds")) . "```",
                                $divisor % 3 !== 0
                            );
                            $divisor++;
                        }
                        $messageBuilder->addEmbed($embed);
                    }
                    $plan->utilities->acknowledgeMessage($interaction, $messageBuilder, true);
                } else {
                    $plan->utilities->acknowledgeMessage(
                        $interaction,
                        MessageBuilder::new()->setContent(
                            "No account history found."
                        ),
                        true
                    );
                }
            } else {
                $plan->utilities->acknowledgeMessage(
                    $interaction,
                    MessageBuilder::new()->setContent(
                        $history->getMessage()
                    ),
                    true
                );
            }
        } else {
            $plan->component->showModal($interaction, "0-log_in");
        }
    }

    public static function view_support_code(DiscordPlan    $plan,
                                             Interaction    $interaction,
                                             MessageBuilder $messageBuilder,
                                             mixed          $objects): void
    {
        $account = self::getAccountSession($plan, $interaction->user->id);
        $account = $account->getSession();

        if ($account->isPositiveOutcome()) {
            $account = $account->getObject();
            $messageBuilder = MessageBuilder::new();
            $embed = new Embed($plan->discord);
            $embed->setTitle($account->getIdentification()->get());
            $embed->setDescription("Send this code when asked by our team to help us identify you.");
            $messageBuilder->addEmbed($embed);
            $plan->utilities->acknowledgeMessage($interaction, $messageBuilder, true);
        } else {
            $plan->component->showModal($interaction, "0-log_in");
        }
    }

    public static function toggle_settings(DiscordPlan    $plan,
                                           Interaction    $interaction,
                                           MessageBuilder $messageBuilder,
                                           mixed          $objects): void
    {
        $account = self::getAccountSession($plan, $interaction->user->id);
        $account = $account->getSession();

        if ($account->isPositiveOutcome()) {
            $account = $account->getObject();
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
        $account = self::getAccountSession($plan, $interaction->user->id);
        $account = $account->getSession();

        if ($account->isPositiveOutcome()) {
            $account = $account->getObject();
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
                            ->setPlaceholder("Please insert the credential here.")
                    ),
                    null,
                    function (Interaction $interaction, Collection $components)
                    use ($plan, $account, $selectedAccountID) {
                        if (!$plan->component->hasCooldown($interaction)) {
                            $components = $components->toArray();
                            $credential = array_shift($components)["value"];
                            $plan->utilities->acknowledgeMessage(
                                $interaction,
                                MessageBuilder::new()->setContent(
                                    $account->getAccounts()->add($selectedAccountID, $credential)->getMessage()
                                ), true
                            );
                        }
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
        $account = self::getAccountSession($plan, $interaction->user->id);
        $account = $account->getSession();

        if ($account->isPositiveOutcome()) {
            $account = $account->getObject();
            $selectedAccountID = $objects[0]->getValue();
            $selectedAccountName = $account->getAccounts()->getAvailable(array("name"), $selectedAccountID);

            if (!empty($selectedAccountName)) {
                $accounts = $account->getAccounts()->getAdded($selectedAccountID, 25);

                if (!empty($accounts)) {
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
                    }, $plan->discord);
                    $messageBuilder->addComponent($select);
                    $plan->utilities->acknowledgeMessage($interaction, $messageBuilder, true);
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
        } else {
            $plan->component->showModal($interaction, "0-log_in");
        }
    }

    public static function toggle_settings_click(DiscordPlan    $plan,
                                                 Interaction    $interaction,
                                                 MessageBuilder $messageBuilder,
                                                 mixed          $objects): void
    {
        $account = self::getAccountSession($plan, $interaction->user->id);
        $account = $account->getSession();

        if ($account->isPositiveOutcome()) {
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
        $account = self::getAccountSession($plan, $interaction->user->id);
        $account = $account->getSession();

        if ($account->isPositiveOutcome()) {
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
        $account = self::getAccountSession($plan, $interaction->user->id);
        $account = $account->getSession();

        if ($account->isPositiveOutcome()) {
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
        $account = self::getAccountSession($plan, $interaction->user->id);
        $account = $account->getSession();

        if ($account->isPositiveOutcome()) {
            $plan->component->showModal($interaction, "0-contact_form");
        } else {
            $plan->component->showModal($interaction, "0-log_in");
        }
    }
}