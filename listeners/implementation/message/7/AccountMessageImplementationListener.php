<?php

use Discord\Builders\Components\TextInput;
use Discord\Builders\MessageBuilder;
use Discord\Helpers\Collection;
use Discord\Parts\Embed\Embed;
use Discord\Parts\Interactions\Interaction;

class AccountMessageImplementationListener
{

    public static function getAccountSession(DiscordPlan $plan, int|string $userID): object
    {
        $application = new Application($plan->applicationID);
        $session = $application->getAccountSession();
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
        $interaction->respondWithMessage(
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
            $plan->component->showModal($interaction, "0-change_email");
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
            $account = $account->getObject();
            $interaction->respondWithMessage(
                MessageBuilder::new()->setContent(
                    $account->getPassword()->requestChange()->getMessage()
                ),
                true
            );
        } else {
            $plan->component->showModal($interaction, "0-log_in");
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
                    $limit = 25.0;
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
                    $interaction->respondWithMessage($messageBuilder, true);
                } else {
                    $interaction->respondWithMessage(
                        MessageBuilder::new()->setContent(
                            "No account history found."
                        ),
                        true
                    );
                }
            } else {
                $interaction->respondWithMessage(
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
            $interaction->respondWithMessage($messageBuilder, true);
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
            $interaction->respondWithMessage(
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
                            $interaction->respondWithMessage(
                                MessageBuilder::new()->setContent(
                                    $account->getAccounts()->add($selectedAccountID, $credential)->getMessage()
                                ), true
                            );
                        }
                    },
                );
            } else {
                $interaction->respondWithMessage(
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
            $interaction->respondWithMessage(
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
            $interaction->respondWithMessage(
                $plan->component->addSelection($interaction, MessageBuilder::new(), "0-connect_accounts"),
                true
            );
        } else {
            $plan->component->showModal($interaction, "0-log_in");
        }
    }
}