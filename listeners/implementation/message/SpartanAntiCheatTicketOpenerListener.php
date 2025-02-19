<?php

use Discord\Builders\MessageBuilder;
use Discord\Parts\Interactions\Interaction;

class SpartanAntiCheatTicketOpenerListener
{

    public static function open(DiscordBot     $bot,
                                Interaction    $interaction,
                                MessageBuilder $messageBuilder,
                                mixed          $objects): void // Name can be changed
    {
        $bot->component->showModal(
            $interaction,
            "0-spartan_anti_cheat_ticket"
        );
    }

}