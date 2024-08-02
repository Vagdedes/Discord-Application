<?php

use Discord\Builders\MessageBuilder;
use Discord\Parts\Interactions\Interaction;

class StaticNodeCommandImplementationListener
{

    public static function create_ticket(DiscordPlan $plan,
                                         Interaction $interaction,
                                         object      $command): void
    {
        // channel.role.user
        $channel = $interaction->data?->resolved?->channels?->first();

        if ($channel !== null
            && ($channel->allowText()
                || $channel->allowInvite()
                || $channel->allowVoice())) {
            $plan->utilities->acknowledgeCommandMessage(
                $interaction,
                MessageBuilder::new()->setContent(
                    "Channel must be a category channel, not text, voice or allow invites."
                ),
                true
            );
            return;
        }
        $arguments = $interaction->data->options->toArray();
        $permissions = new stdClass();
        $permissions->allow = 0;
        $permissions->deny = 0;

        $plan->userTickets->create(
            $interaction,
            null,
            $channel === null ? 0 : $channel->id,
            $arguments["channel-name"]["value"],
            null,
            null,
            null,
            array(
                $interaction->data?->resolved?->roles?->first()?->id => $permissions
            ),
            null
        );
    }

}