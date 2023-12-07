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
    private const
        VISIONARY_ID = 1174011691764285514,
        INVESTOR_ID = 1105149269683478661,
        SPONSOR_ID = 1105149280764821634,
        MOTIVATOR_ID = 1105149288318779392;

    public static function getAccountSession(Interaction $interaction,
                                             DiscordPlan $plan): object
    {
        $account = new Account($plan->applicationID);
        $session = $account->getSession();
        $session->setCustomKey("discord", $interaction->member->id);
        $account = $session->getSession();

        if ($account->isPositiveOutcome()) {
            $permissions = $account->getObject()->getPermissions();

            if ($permissions->hasPermission("patreon.subscriber.visionary")) {
                $plan->permissions->addDiscordRole($interaction->member, self::VISIONARY_ID);
                $plan->permissions->removeDiscordRole($interaction->member, self::INVESTOR_ID);
                $plan->permissions->removeDiscordRole($interaction->member, self::SPONSOR_ID);
                $plan->permissions->removeDiscordRole($interaction->member, self::MOTIVATOR_ID);
            } else if ($permissions->hasPermission("patreon.subscriber.investor")) {
                $plan->permissions->addDiscordRole($interaction->member, self::INVESTOR_ID);
                $plan->permissions->removeDiscordRole($interaction->member, self::VISIONARY_ID);
                $plan->permissions->removeDiscordRole($interaction->member, self::SPONSOR_ID);
                $plan->permissions->removeDiscordRole($interaction->member, self::MOTIVATOR_ID);
            } else if ($permissions->hasPermission("patreon.subscriber.sponsor")) {
                $plan->permissions->addDiscordRole($interaction->member, self::SPONSOR_ID);
                $plan->permissions->removeDiscordRole($interaction->member, self::VISIONARY_ID);
                $plan->permissions->removeDiscordRole($interaction->member, self::INVESTOR_ID);
                $plan->permissions->removeDiscordRole($interaction->member, self::MOTIVATOR_ID);
            } else if ($permissions->hasPermission("patreon.subscriber.motivator")) {
                $plan->permissions->addDiscordRole($interaction->member, self::MOTIVATOR_ID);
                $plan->permissions->removeDiscordRole($interaction->member, self::VISIONARY_ID);
                $plan->permissions->removeDiscordRole($interaction->member, self::INVESTOR_ID);
                $plan->permissions->removeDiscordRole($interaction->member, self::SPONSOR_ID);
            } else {
                $plan->permissions->removeDiscordRole($interaction->member, self::VISIONARY_ID);
                $plan->permissions->removeDiscordRole($interaction->member, self::INVESTOR_ID);
                $plan->permissions->removeDiscordRole($interaction->member, self::SPONSOR_ID);
                $plan->permissions->removeDiscordRole($interaction->member, self::MOTIVATOR_ID);
            }
        }
        return $session;
    }

    public static function my_account(DiscordPlan    $plan,
                                      Interaction    $interaction,
                                      MessageBuilder $messageBuilder,
                                      mixed          $objects): void
    {
        $account = self::getAccountSession($interaction, $plan);
        $account = $account->getSession();

        if ($account->isPositiveOutcome()) {
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
        $account = self::getAccountSession($interaction, $plan);
        $account = $account->getSession();

        if ($account->isPositiveOutcome()) {
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
        $account = self::getAccountSession($interaction, $plan);
        $account = $account->getSession();

        if ($account->isPositiveOutcome()) {
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
        $account = self::getAccountSession($interaction, $plan);
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
        $account = self::getAccountSession($interaction, $plan);
        $account = $account->getSession();

        if ($account->isPositiveOutcome()) {
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
        $account = self::getAccountSession($interaction, $plan);
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
        $account = self::getAccountSession($interaction, $plan);
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
        $account = self::getAccountSession($interaction, $plan);
        $account = $account->getSession();

        if ($account->isPositiveOutcome()) {
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
        $account = self::getAccountSession($interaction, $plan);
        $account = $account->getSession();

        if ($account->isPositiveOutcome()) {
            $account = $account->getObject();

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
        $account = self::getAccountSession($interaction, $plan);
        $account = $account->getSession();

        if ($account->isPositiveOutcome()) {
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
        $account = self::getAccountSession($interaction, $plan);
        $account = $account->getSession();

        if ($account->isPositiveOutcome()) {
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
        $account = self::getAccountSession($interaction, $plan);
        $account = $account->getSession();

        if ($account->isPositiveOutcome()) {
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
        $account = self::getAccountSession($interaction, $plan);
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
        $account = self::getAccountSession($interaction, $plan);
        $account = $account->getSession();

        if ($account->isPositiveOutcome()) {
            $account = $account->getObject();
            $history = $account->getHistory()->get(
                array("action_id", "creation_date"),
                DiscordInheritedLimits::MAX_CHOICES_PER_SELECTION * DiscordInheritedLimits::MAX_FIELDS_PER_EMBED
            );

            if ($history->isPositiveOutcome()) {
                $history = $history->getObject();
                $size = sizeof($history);

                if ($size > 0) {
                    $limit = DiscordInheritedLimits::MAX_FIELDS_PER_EMBED;
                    $messageBuilder = MessageBuilder::new();
                    $select = SelectMenu::new()
                        ->setMaxValues(1)
                        ->setMinValues(1)
                        ->setPlaceholder("Select a time period.");

                    for ($i = 0; $i < ceil($size / $limit); $i++) {
                        $counter = $i * $limit;
                        $max = min($counter + $limit, $size);
                        $select->addOption(Option::new(
                            get_full_date($history[$counter]->creation_date)
                            . " - "
                            . get_full_date($history[$max - 1]->creation_date),
                            $i
                        ));
                    }
                    $select->setListener(function (Interaction $interaction, Collection $options)
                    use ($size, $plan, $select, $history, $limit) {
                        if (!$plan->component->hasCooldown($select)) {
                            $count = $options[0]->getValue();
                            $messageBuilder = MessageBuilder::new();

                            $counter = $count * $limit;
                            $max = min($counter + $limit, $size);
                            $divisor = 0;
                            $embed = new Embed($plan->discord);
                            $embed->setTitle("Account History");
                            $embed->setDescription(
                                get_full_date($history[$counter]->creation_date)
                                . " - "
                                . get_full_date($history[$max - 1]->creation_date)
                            );

                            for ($x = $counter; $x < $max; $x++) {
                                $row = $history[$x];
                                $embed->addFieldValues(
                                    "__" . ($x + 1) . "__ " . str_replace("_", "-", $row->action_id),
                                    "```" . get_full_date($row->creation_date) . "```",
                                    $divisor % 3 !== 0
                                );
                                $divisor++;
                            }
                            $messageBuilder->addEmbed($embed);
                            $plan->utilities->acknowledgeMessage($interaction, $messageBuilder, true);
                        }
                    }, $plan->discord);
                    $messageBuilder->addComponent($select);
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
        $account = self::getAccountSession($interaction, $plan);
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
        $account = self::getAccountSession($interaction, $plan);
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
        $account = self::getAccountSession($interaction, $plan);
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
                            ->setPlaceholder("Insert the credential here.")
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
        $account = self::getAccountSession($interaction, $plan);
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
        $account = self::getAccountSession($interaction, $plan);
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
        $account = self::getAccountSession($interaction, $plan);
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
        $account = self::getAccountSession($interaction, $plan);
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
        $account = self::getAccountSession($interaction, $plan);
        $account = $account->getSession();

        if ($account->isPositiveOutcome()) {
            $plan->component->showModal($interaction, "0-contact_form");
        } else {
            $plan->component->showModal($interaction, "0-log_in");
        }
    }
}