<?php

use Discord\Builders\MessageBuilder;
use Discord\Parts\Channel\Channel;
use Discord\Parts\Channel\Message;
use Discord\Parts\Embed\Embed;
use Discord\Parts\Guild\Guild;
use Discord\Parts\Interactions\Interaction;
use Discord\Parts\Thread\Thread;
use Discord\Parts\User\Member;
use Discord\Parts\User\User;
use React\EventLoop\Loop;

class DiscordUtilities
{

    private DiscordBot $bot;

    public function __construct(DiscordBot $bot)
    {
        $this->bot = $bot;
    }

    public function allowText(Channel|Thread $channel): bool
    {
        if ($channel instanceof Channel) {
            return $channel->allowText();
        } else {
            return $channel->parent->allowText();
        }
    }

    public function getUser(int|string $userID): ?object
    {
        $users = $this->bot->discord->users->toArray();
        return $users[$userID] ?? null;
    }

    public function getMember(Guild $guild, int|string|User $userID): ?object
    {
        $members = $guild->members->toArray();
        return $members[$userID instanceof User ? $userID->id : $userID] ?? null;
    }

    public function getUsername(int|string $userID): string
    {
        $users = $this->bot->discord->users->toArray();
        return $users[$userID]?->username ?? $userID;
    }

    public function getAvatar(int|string $userID): ?string
    {
        $users = $this->bot->discord->users->toArray();
        return $users[$userID]?->avatar ?? null;
    }

    // Separator

    public function getGuild(int|string $serverID): ?Guild
    {
        return $this->bot->discord->guilds->toArray()[$serverID] ?? null;
    }

    // Separator

    public function getChannelOrThread(Channel|Thread $channel): Channel
    {
        return $channel instanceof Thread ? $channel->parent : $channel;
    }

    public function getChannel(Channel|Thread $channel): ?Channel
    {
        return $channel instanceof Thread ? null : $channel;
    }

    public function getThread(Channel|Thread $channel): ?Thread
    {
        return $channel instanceof Thread ? $channel : null;
    }

    public function createChannel(Guild|int|string $guild,
                                  int              $type, int|string|null $parent,
                                  int|string|float $name, int|string|float|null $topic,
                                  array            $rolePermissions = null,
                                  array            $memberPermissions = null): ?\React\Promise\ExtendedPromiseInterface
    {
        if (!($guild instanceof Guild)) {
            $guild = $this->getGuild($guild);

            if ($guild === null) {
                return null;
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
            $channel = $this->bot->discord->getChannel($channel);

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

    public function acknowledgeMessage(Interaction             $interaction,
                                       MessageBuilder|callable $messageBuilder,
                                       bool                    $ephemeral): void
    {
        $interaction->acknowledge()->done(function () use ($interaction, $messageBuilder, $ephemeral) {
            if ($messageBuilder instanceof MessageBuilder) {
                $interaction->sendFollowUpMessage($messageBuilder, $ephemeral);
            } else {
                $interaction->sendFollowUpMessage($messageBuilder(), $ephemeral);
            }
        });
    }

    public function acknowledgeCommandMessage(Interaction    $interaction,
                                              MessageBuilder $messageBuilder,
                                              bool           $ephemeral): mixed
    {
        return $interaction->respondWithMessage($messageBuilder, $ephemeral);
    }

    // Separator

    public function replyMessage(Message|int|string $message, MessageBuilder|string $messageBuilder,
                                 ?MessageBuilder    $default = null, array $embeds = []): void
    {
        if ($default === null) {
            $default = MessageBuilder::new();
        }
        try {
            $message->channel?->messages->fetch(
                $message instanceof Message ? $message->id : $message,
                true
            )->done(function (Message $message) use ($messageBuilder, $default, $embeds) {
                if ($messageBuilder instanceof MessageBuilder) {
                    $builder = $messageBuilder;
                } else {
                    $builder = clone $default;
                    $builder->setEmbeds($embeds)->setContent($messageBuilder);
                }
                $message->reply($builder);
            });
        } catch (Throwable $ignored) {
        }
    }

    public function replyMessageInPieces(Message         $message, string $reply,
                                         ?MessageBuilder $default = null, array $embeds = []): void
    {
        if ($default === null) {
            $default = MessageBuilder::new();
        }
        $pieces = str_split($reply, DiscordInheritedLimits::MESSAGE_MAX_LENGTH);
        $this->editMessage(
            $message,
            array_shift($pieces),
            $default,
            $embeds
        );

        if (!empty($pieces)) {
            foreach ($pieces as $split) {
                $builder = clone $default;
                $this->replyMessage(
                    $message,
                    $builder->setContent($split)
                );
            }
        }
    }

    public function sendMessageInPieces(Member|User     $member, string $reply,
                                        ?MessageBuilder $default = null, array $embeds = []): void
    {
        if ($default === null) {
            $default = MessageBuilder::new();
        }
        $pieces = str_split($reply, DiscordInheritedLimits::MESSAGE_MAX_LENGTH);
        $member->sendMessage(MessageBuilder::new()->setContent(array_shift($pieces))->setEmbeds($embeds));

        if (!empty($pieces)) {
            foreach (str_split($reply, DiscordInheritedLimits::MESSAGE_MAX_LENGTH) as $split) {
                $builder = clone $default;
                $member->sendMessage($builder->setContent($split));
            }
        }
    }

    public function editMessage(Message|int|string $message, MessageBuilder|string $messageBuilder,
                                ?MessageBuilder    $default = null, array $embeds = []): void
    {
        if ($default === null) {
            $default = MessageBuilder::new();
        }
        try {
            $message->channel?->messages->fetch(
                $message instanceof Message ? $message->id : $message,
                true
            )->done(function (Message $message) use ($messageBuilder, $default, $embeds) {
                if ($messageBuilder instanceof MessageBuilder) {
                    $message->edit($messageBuilder);
                } else {
                    $builder = clone $default;
                    $message->edit($builder->setEmbeds($embeds)->setContent($messageBuilder));
                }
            });
        } catch (Throwable $ignored) {
        }
    }

    public function deleteMessage(Message|int|string $message): void
    {
        try {
            $message->channel->messages->fetch(
                $message instanceof Message ? $message->id : $message,
                true
            )->done(function (Message $message) {
                $message->delete();
            });
        } catch (Throwable $ignored) {
        }
    }

    public function buildMessageFromObject(object $row, $object = null): ?MessageBuilder
    {
        $hasContent = !empty($row->message_content);
        $messageBuilder = MessageBuilder::new()->setContent(
            $this->bot->instructions->replace(array($hasContent ? $row->message_content : ""), $object)[0]
        );
        $embed = new Embed($this->bot->discord);
        $addEmbed = false;

        if (!empty($row->embed_title)) {
            $embed->setTitle($this->bot->instructions->replace(array($row->embed_title), $object)[0]);
            $addEmbed = true;
        }
        if (!empty($row->embed_description)) {
            $embed->setDescription($this->bot->instructions->replace(array($row->embed_description), $object)[0]);
            $addEmbed = true;
        }
        if (!empty($row->embed_url)) {
            $embed->setUrl($row->embed_url);
            $addEmbed = true;
        }
        if (!empty($row->embed_color)) {
            $embed->setColor($row->embed_color);
            $addEmbed = true;
        }
        if (!empty($row->embed_image)) {
            $embed->setImage($row->embed_image);
            $addEmbed = true;
        }
        if (!empty($row->embed_timestamp)) {
            $embed->setTimestamp(strtotime($row->embed_timestamp));
            $addEmbed = true;
        }
        if (!empty($row->embed_footer)) {
            $embed->setFooter($this->bot->instructions->replace(array($row->embed_footer), $object)[0]);
            $addEmbed = true;
        }
        if (!empty($row->embed_author_name)) {
            $embed->setAuthor(
                $this->bot->instructions->replace(array($row->embed_author_name), $object)[0],
                $row->embed_author_icon_url,
                $row->embed_author_url,
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

    public function hash(mixed ...$strings): int|float
    {
        return string_to_integer(implode("", $strings), true);
    }

    public function getServersQuery(): array
    {
        $array = array();

        if (!empty($this->bot->discord->guilds->first())) {
            $array[] = null;

            foreach ($this->bot->discord->guilds as $guild) {
                $array[] = array("server_id", "=", $guild->id, 0);
            }
            $array[] = array("server_id", "IS", null);
            $array[] = null;
        }
        return $array;
    }
}