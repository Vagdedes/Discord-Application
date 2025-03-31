<?php

use Discord\Builders\MessageBuilder;
use Discord\Parts\Interactions\Interaction;

class AntiCheatTicketOpenerListener
{

    public static function open(DiscordBot     $bot,
                                Interaction    $interaction,
                                MessageBuilder $messageBuilder,
                                mixed          $objects): void // Name can be changed
    {
        if (!$bot->userTickets->call(
            $interaction,
            "0-anti_cheat_ticket"
        )) {
            $bot->utilities->acknowledgeMessage(
                $interaction,
                MessageBuilder::new()->setContent("Failed to open the ticket."),
                true
            );
        }
    }

}