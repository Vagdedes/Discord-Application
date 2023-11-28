<?php

use Discord\Builders\MessageBuilder;
use Discord\Parts\Channel\Channel;
use Discord\Parts\Channel\Message;
use Discord\Parts\Embed\Embed;
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

    // Separator

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

    // Separator

    public function replyMessage(Message|int|string $message, MessageBuilder|string $messageBuilder): void
    {
        try {
            $message->channel?->messages->fetch(
                $message instanceof Message ? $message->id : $message,
            )->done(function (Message $message) use ($messageBuilder) {
                $message->reply($messageBuilder);
            });
        } catch (Throwable $ignored) {
        }
    }

    public function editMessage(Message|int|string $message, MessageBuilder|string $messageBuilder): void
    {
        try {
            $message->channel?->messages->fetch(
                $message instanceof Message ? $message->id : $message,
            )->done(function (Message $message) use ($messageBuilder) {
                $message->edit(
                    $messageBuilder instanceof MessageBuilder ? $messageBuilder
                        : MessageBuilder::new()->setContent($messageBuilder)
                );
            });
        } catch (Throwable $ignored) {
        }
    }

    public function deleteMessage(Message|int|string $message): void
    {
        try {
            $message->channel->messages->fetch(
                $message instanceof Message ? $message->id : $message,
            )->done(function (Message $message) {
                $message->delete();
            });
        } catch (Throwable $ignored) {
        }
    }

    public function buildMessageFromObject(object $object): ?MessageBuilder
    {
        $hasContent = !empty($object->message_content);
        $messageBuilder = MessageBuilder::new()->setContent(
            $hasContent ? $object->message_content : ""
        );
        $embed = new Embed($this->plan->discord);
        $addEmbed = false;

        if (!empty($object->embed_title)) {
            $embed->setTitle($object->embed_title);
            $addEmbed = true;
        }
        if (!empty($object->embed_description)) {
            $embed->setDescription($object->embed_description);
            $addEmbed = true;
        }
        if (!empty($object->embed_url)) {
            $embed->setUrl($object->embed_url);
            $addEmbed = true;
        }
        if ($object->embed_color !== null) {
            $embed->setColor($object->embed_color);
            $addEmbed = true;
        }
        if ($object->embed_image !== null) {
            $embed->setImage($object->embed_image);
            $addEmbed = true;
        }
        if ($object->embed_timestamp !== null) {
            $embed->setTimestamp(strtotime($object->embed_timestamp));
            $addEmbed = true;
        }
        if ($object->embed_footer !== null) {
            $embed->setFooter($object->embed_footer);
            $addEmbed = true;
        }
        if (!empty($object->embed_author_name)) {
            $embed->setAuthor(
                $object->embed_author_name,
                $object->embed_author_icon_url,
                $object->embed_author_url,
            );
            $addEmbed = true;
        }
        if ($addEmbed) {
            $messageBuilder->addEmbed($embed);
        } else if (!$hasContent) {
            return null;
        }
        return $messageBuilder;
    }
}