<?php

use Discord\Parts\Channel\Message;

class TestingAITextImplementationListener // Name can be changed
{

    public static function test_method(object     $model,
                                       DiscordBot $bot,
                                       Message    $originalMessage,
                                       object     $channel,
                                       ?array     $localInstructions,
                                       ?array     $publicInstructions): array // Name can be changed
    {
        return array($localInstructions, $publicInstructions);
    }
}