<?php

use Discord\Builders\MessageBuilder;
use Discord\Parts\Interactions\Interaction;

class AccountMessageImplementationListener
{

    public static function getAccountSession(DiscordPlan $plan, Interaction $interaction): object
    {
        $application = new Application($plan->applicationID);
        $session = $application->getAccountSession();
        $session->setCustomKey("discord", $interaction->user->id);
        return $session;
    }

    public static function my_account(DiscordPlan    $plan,
                                      Interaction    $interaction,
                                      MessageBuilder $messageBuilder,
                                      mixed          $objects): void
    {
        $account = self::getAccountSession($plan, $interaction);
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
        $account = self::getAccountSession($plan, $interaction);
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
        $account = self::getAccountSession($plan, $interaction);
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
        $account = self::getAccountSession($plan, $interaction);
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
        $account = self::getAccountSession($plan, $interaction);
        $account = $account->getSession();

        if ($account->isPositiveOutcome()) {
            //todo
        } else {
            $plan->component->showModal($interaction, "0-log_in");
        }
    }

    public static function change_password(DiscordPlan    $plan,
                                           Interaction    $interaction,
                                           MessageBuilder $messageBuilder,
                                           mixed          $objects): void
    {
        $account = self::getAccountSession($plan, $interaction);
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
        $account = self::getAccountSession($plan, $interaction);
        $account = $account->getSession();

        if ($account->isPositiveOutcome()) {
            //todo
        } else {
            $plan->component->showModal($interaction, "0-log_in");
        }
    }

    public static function view_history(DiscordPlan    $plan,
                                        Interaction    $interaction,
                                        MessageBuilder $messageBuilder,
                                        mixed          $objects): void
    {
        $account = self::getAccountSession($plan, $interaction);
        $account = $account->getSession();

        if ($account->isPositiveOutcome()) {
            //todo
        } else {
            $plan->component->showModal($interaction, "0-log_in");
        }
    }

    public static function view_support_code(DiscordPlan    $plan,
                                             Interaction    $interaction,
                                             MessageBuilder $messageBuilder,
                                             mixed          $objects): void
    {
        $account = self::getAccountSession($plan, $interaction);
        $account = $account->getSession();

        if ($account->isPositiveOutcome()) {
            //todo
        } else {
            $plan->component->showModal($interaction, "0-log_in");
        }
    }

    public static function toggle_settings(DiscordPlan    $plan,
                                           Interaction    $interaction,
                                           MessageBuilder $messageBuilder,
                                           mixed          $objects): void
    {
        $account = self::getAccountSession($plan, $interaction);
        $account = $account->getSession();

        if ($account->isPositiveOutcome()) {
            //todo
        } else {
            $plan->component->showModal($interaction, "0-log_in");
        }
    }

    public static function connect_account(DiscordPlan    $plan,
                                           Interaction    $interaction,
                                           MessageBuilder $messageBuilder,
                                           mixed          $objects): void
    {
        $account = self::getAccountSession($plan, $interaction);
        $account = $account->getSession();

        if ($account->isPositiveOutcome()) {
            //todo
        } else {
            $plan->component->showModal($interaction, "0-log_in");
        }
    }

    public static function toggle_settings_click(DiscordPlan    $plan,
                                                 Interaction    $interaction,
                                                 MessageBuilder $messageBuilder,
                                                 mixed          $objects): void
    {
        $account = self::getAccountSession($plan, $interaction);
        $account = $account->getSession();

        if ($account->isPositiveOutcome()) {
            $interaction->respondWithMessage(
                $plan->component->addSelection(MessageBuilder::new(), "0-toggle_settings"),
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
        $account = self::getAccountSession($plan, $interaction);
        $account = $account->getSession();

        if ($account->isPositiveOutcome()) {
            $interaction->respondWithMessage(
                $plan->component->addSelection(MessageBuilder::new(), "0-connect_accounts"),
                true
            );
        } else {
            $plan->component->showModal($interaction, "0-log_in");
        }
    }
}