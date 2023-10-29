<?php

use Discord\Builders\MessageBuilder;
use Discord\Parts\Interactions\Interaction;

class AccountImplementationListener
{

    private static function getAccountSession(DiscordPlan $plan, Interaction $interaction): object
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

        if (true || $account->isPositiveOutcome()) {
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
    }

    public static function log_in(DiscordPlan    $plan,
                                  Interaction    $interaction,
                                  MessageBuilder $messageBuilder,
                                  mixed          $objects): void
    {

    }

    public static function change_email(DiscordPlan    $plan,
                                        Interaction    $interaction,
                                        MessageBuilder $messageBuilder,
                                        mixed          $objects): void
    {

    }

    public static function change_password(DiscordPlan    $plan,
                                           Interaction    $interaction,
                                           MessageBuilder $messageBuilder,
                                           mixed          $objects): void
    {

    }

    public static function change_username(DiscordPlan    $plan,
                                           Interaction    $interaction,
                                           MessageBuilder $messageBuilder,
                                           mixed          $objects): void
    {

    }

    public static function view_history(DiscordPlan    $plan,
                                        Interaction    $interaction,
                                        MessageBuilder $messageBuilder,
                                        mixed          $objects): void
    {

    }

    public static function view_support_code(DiscordPlan    $plan,
                                             Interaction    $interaction,
                                             MessageBuilder $messageBuilder,
                                             mixed          $objects): void
    {

    }

    public static function toggle_settings(DiscordPlan    $plan,
                                           Interaction    $interaction,
                                           MessageBuilder $messageBuilder,
                                           mixed          $objects): void
    {

    }

    public static function connect_account(DiscordPlan    $plan,
                                           Interaction    $interaction,
                                           MessageBuilder $messageBuilder,
                                           mixed          $objects): void
    {

    }

    public static function toggle_settings_click(DiscordPlan    $plan,
                                                 Interaction    $interaction,
                                                 MessageBuilder $messageBuilder,
                                                 mixed          $objects): void
    {
        $interaction->respondWithMessage(
            $plan->component->addSelection(MessageBuilder::new(), "0-toggle_settings"),
            true
        );
    }

    public static function connect_account_click(DiscordPlan    $plan,
                                                 Interaction    $interaction,
                                                 MessageBuilder $messageBuilder,
                                                 mixed          $objects): void
    {
        $interaction->respondWithMessage(
            $plan->component->addSelection(MessageBuilder::new(), "0-connect_accounts"),
            true
        );
    }

    public static function toggle_settings_redirect(DiscordPlan    $plan,
                                                    Interaction    $interaction,
                                                    MessageBuilder $messageBuilder,
                                                    mixed          $objects): void
    {
        $interaction->respondWithMessage(
            $interaction->message->
            true
        );
    }

    public static function connect_account_redirect(DiscordPlan $plan,
                                                    Interaction $interaction,
                                                    mixed       $objects): void
    {

    }
}