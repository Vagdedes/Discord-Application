<?php

use Discord\Parts\WebSockets\MessageReaction;

class AccountReactionCreationListener
{

    public static function feedback_positive(DiscordBot      $bot,
                                             MessageReaction $reaction): void
    {
        $bot->aiMessages->sendFeedback($reaction, 1);
    }

    public static function feedback_negative(DiscordBot      $bot,
                                             MessageReaction $reaction): void
    {
        $bot->aiMessages->sendFeedback($reaction, -1);
    }

    public static function feedback_neutral(DiscordBot      $bot,
                                            MessageReaction $reaction): void
    {
        $bot->aiMessages->sendFeedback($reaction, null);
    }
}