<?php

use Discord\Parts\WebSockets\MessageReaction;

class AccountReactionCreationListener
{

    public static function feedback_positive(DiscordPlan     $plan,
                                             MessageReaction $reaction): void
    {
        $plan->aiMessages->sendFeedback($reaction, 1);
    }

    public static function feedback_negative(DiscordPlan     $plan,
                                             MessageReaction $reaction): void
    {
        $plan->aiMessages->sendFeedback($reaction, -1);
    }

    public static function feedback_neutral(DiscordPlan     $plan,
                                            MessageReaction $reaction): void
    {
        $plan->aiMessages->sendFeedback($reaction, 0);
    }
}