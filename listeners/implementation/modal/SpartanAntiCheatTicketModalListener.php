<?php

use Discord\Parts\Interactions\Interaction;

class SpartanAntiCheatTicketModalListener
{

    public static function ticket(DiscordBot  $bot,
                                  Interaction $interaction,
                                  mixed       $objects): void // Name can be changed
    {
        $bot->userTickets->call(
            $interaction,
            "0-spartan_anti_cheat_ticket"
        );
    }

}