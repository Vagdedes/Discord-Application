<?php

use Discord\Builders\MessageBuilder;
use Discord\Parts\Interactions\Interaction;

class TestingMessageImplementationListener // Name can be changed
{

    public static function test_method(DiscordBot    $bot,
                                       Interaction    $interaction,
                                       MessageBuilder $messageBuilder,
                                       mixed          $objects): void // Name can be changed
    {
        $bot->utilities->acknowledgeMessage(
            $interaction,
            MessageBuilder::new()->setContent(@json_encode($objects)),
            true
        );
    }
}