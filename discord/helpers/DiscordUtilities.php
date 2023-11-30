<?php

use Discord\Builders\MessageBuilder;
use Discord\Discord;
use Discord\Parts\Channel\Channel;
use Discord\Parts\Channel\Message;
use Discord\Parts\Embed\Embed;
use Discord\Parts\Guild\Guild;
use Discord\Parts\Interactions\Interaction;
use Discord\Parts\Thread\Thread;

class DiscordUtilities
{

    private Discord $discord;

    public function __construct(Discord $discord)
    {
        $this->discord = $discord;
    }

    public function getUser(int|string $userID): ?object
    {
        $users = $this->discord->users->toArray();
        return $users[$userID] ?? null;
    }

    public function getUsername(int|string $userID): string
    {
        $users = $this->discord->users->toArray();
        return $users[$userID]?->username ?? $userID;
    }

    public function getAvatar(int|string $userID): ?string
    {
        $users = $this->discord->users->toArray();
        return $users[$userID]?->avatar ?? null;
    }

    // Separator

    public function getGuild(int|string $serverID): ?Guild
    {
        return $this->discord->guilds->toArray()[$serverID] ?? null;
    }

    // Separator

    public function createChannel(Guild|int|string $guild,
                                  int              $type, int|string|null $parent,
                                  int|string|float $name, int|string|float|null $topic,
                                  array            $rolePermissions = null,
                                  array            $memberPermissions = null): bool|\React\Promise\ExtendedPromiseInterface
    {
        if (!($guild instanceof Guild)) {
            $guild = $this->getGuild($guild);

            if ($guild === null) {
                return false;
            }
        }
        $permissions = array();

        if (!empty($rolePermissions)) {
            foreach ($rolePermissions as $permission) {
                if (!array_key_exists("id", $permission)) {
                    $permission["id"] = $guild->id;
                }
                $permission["type"] = "role";
                $permissions[] = $permission;
            }
        }
        if (!empty($memberPermissions)) {
            foreach ($memberPermissions as $permission) {
                $permission["type"] = "member";
                $permissions[] = $permission;
            }
        }
        $parameters = array(
            "name" => $name,
            "type" => $type,
            "permission_overwrites" => $permissions
        );

        if ($parent !== null) {
            $parameters["parent_id"] = $parent;
        }
        if ($topic !== null) {
            $parameters["topic"] = $topic;
        }
        return $guild->channels->save(
            $guild->channels->create(
                $parameters
            )
        );
    }

    // Separator

    public function deleteThread(int|string|Channel    $channel,
                                 int|string|Thread     $thread,
                                 string|null|float|int $reason = null): bool
    {
        if (!($channel instanceof Channel)) {
            $channel = $this->discord->getChannel($channel);

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
        $embed = new Embed($this->discord);
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