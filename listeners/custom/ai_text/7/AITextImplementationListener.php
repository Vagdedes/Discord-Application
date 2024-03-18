<?php

use Discord\Parts\Channel\Message;

class AITextImplementationListener // Name can be changed
{

    public static function method(DiscordPlan $plan,
                                       Message     $originalMessage,
                                       object      $channel,
                                       ?array      $localInstructions,
                                       ?array      $publicInstructions): array // Name can be changed
    {
        return array($localInstructions, $publicInstructions);
    }
}