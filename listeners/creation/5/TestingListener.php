<?php

use Discord\Builders\MessageBuilder;
use Discord\Discord;

class TestingListener // Name can be changed
{

    public static function test_method(Discord        $discord,
                                       MessageBuilder $messageBuilder): MessageBuilder // Name can be changed
    {
        $messageBuilder->setContent("Hello World!");
        return $messageBuilder;
    }
}