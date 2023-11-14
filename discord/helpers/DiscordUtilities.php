<?php

use Discord\Builders\MessageBuilder;
use Discord\Parts\Guild\Guild;
use Discord\Parts\Interactions\Interaction;

class DiscordUtilities
{

    private DiscordPlan $plan;

    public function __construct(DiscordPlan $plan)
    {
        $this->plan = $plan;
    }

    public function getUsername(int|string $userID): string
    {
        $users = $this->plan->discord->users->getIterator();
        return $users[$userID]?->username ?? $userID;
    }

    public function createChannel(Guild  $guild,
                                  int    $type, int|string $parent,
                                  string $name, string $topic,
                                  array  $rolePermissions = null, array $memberPermissions = null): \React\Promise\ExtendedPromiseInterface
    {
        $permissions = array();

        if (!empty($rolePermissions)) {
            foreach ($rolePermissions as $permission) {
                $permissions[] = $permission;
            }
        }
        if (!empty($memberPermissions)) {
            foreach ($memberPermissions as $permission) {
                $permissions[] = $permission;
            }
        }
        return $guild->channels->save(
            $guild->channels->create(
                array(
                    "name" => $name,
                    "type" => $type,
                    "parent_id" => $parent,
                    "topic" => $topic,
                    "permission_overwrites" => $permissions
                )
            )
        );
    }

    public function acknowledgeMessage(Interaction    $interaction,
                                       MessageBuilder $messageBuilder,
                                       bool           $ephemeral): void
    {
        $interaction->acknowledge()->done(function () use ($interaction, $messageBuilder, $ephemeral) {
            $interaction->sendFollowUpMessage($messageBuilder, $ephemeral);
        });
    }
}