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
        $permissionEquation = 446676978752;

        $rolePermissions = new stdClass();
        $rolePermissions->allow = $permissionEquation;
        $rolePermissions->deny = 0;

        $everyonePermissions = new stdClass();
        $everyonePermissions->allow = 0;
        $everyonePermissions->deny = $permissionEquation;

        $plan->userTickets->create(
            $interaction,
            null,
            $channel?->id,
            $arguments["channel-name"]["value"],
            null,
            null,
            null,
            array(
                $interaction->data?->resolved?->roles?->first()?->id => $rolePermissions,
                $interaction->guild_id => $everyonePermissions
            ),
            null
        );
        $plan->utilities->acknowledgeCommandMessage(
            $interaction,
            MessageBuilder::new()->setContent(
                "Attempting to create abstract ticket..."
            ),
            true
        );
    }

}