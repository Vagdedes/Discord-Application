<?php

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

    public static function my_account(DiscordPlan $plan,
                                      Interaction $interaction,
                                      mixed       $objects): void
    {
        $account = self::getAccountSession($plan, $interaction);
        $account = $account->getSession();

        if ($account->isPositiveOutcome()) {
            $plan->controlledMessages->send($interaction, "0-logged_in", true);
        } else {
            $plan->controlledMessages->send($interaction, "0-register_or_log_in", true);
        }
    }

    public static function register(DiscordPlan $plan,
                                    Interaction $interaction,
                                    mixed       $objects): void
    {
    }

    public static function log_in(DiscordPlan $plan,
                                  Interaction $interaction,
                                  mixed       $objects): void
    {

    }

    public static function change_email(DiscordPlan $plan,
                                  Interaction $interaction,
                                  mixed       $objects): void
    {

    }

    public static function change_password(DiscordPlan $plan,
                                  Interaction $interaction,
                                  mixed       $objects): void
    {

    }

    public static function change_username(DiscordPlan $plan,
                                  Interaction $interaction,
                                  mixed       $objects): void
    {

    }

    public static function view_history(DiscordPlan $plan,
                                  Interaction $interaction,
                                  mixed       $objects): void
    {

    }

    public static function view_support_code(DiscordPlan $plan,
                                  Interaction $interaction,
                                  mixed       $objects): void
    {

    }

    public static function toggle_settings(DiscordPlan $plan,
                                             Interaction $interaction,
                                             mixed       $objects): void
    {

    }
}