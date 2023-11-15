<?php

use Discord\Builders\MessageBuilder;
use Discord\Parts\Channel\Channel;
use Discord\Parts\Guild\Guild;
use Discord\Parts\Interactions\Interaction;
use Discord\Parts\Thread\Thread;

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

    // Separator

    public function deleteThread(int|string|Channel    $channel,
                                 int|string|Thread     $thread,
                                 string|null|float|int $reason = null): bool
    {
        if (!($channel instanceof Channel)) {
            $channel = $this->plan->discord->getChannel($channel);

            if ($channel === null) {
                return false;
            }
        }
        if (!($thread instanceof Thread)) {
            $thread = $channel->threads->toArray()[$thread];

            if ($thread === null) {
                return false;
            }
        }
        $channel->threads->delete(
            $thread,
            empty($reason) ? null : $reason
        );
        return true;
    }

    // Separator

    public function acknowledgeMessage(Interaction    $interaction,
                                       MessageBuilder $messageBuilder,
                                       bool           $ephemeral): void
    {
        $interaction->acknowledge()->done(function () use ($interaction, $messageBuilder, $ephemeral) {
            $interaction->sendFollowUpMessage($messageBuilder, $ephemeral);
        });
    }

    public function acknowledgeCommandMessage(Interaction    $interaction,
                                              MessageBuilder $messageBuilder,
                                              bool           $ephemeral): void
    {
        $interaction->respondWithMessage($messageBuilder, $ephemeral);
    }
}