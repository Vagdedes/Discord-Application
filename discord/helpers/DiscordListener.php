<?php

use Discord\Builders\Components\TextInput;
use Discord\Builders\MessageBuilder;
use Discord\Parts\Channel\Channel;
use Discord\Parts\Channel\Invite;
use Discord\Parts\Channel\Message;
use Discord\Parts\Guild\Guild;
use Discord\Parts\Interactions\Interaction;
use Discord\Parts\Thread\Thread;
use Discord\Parts\User\Member;
use Discord\Parts\WebSockets\MessageReaction;

class DiscordListener
{
    private DiscordBot $bot;
    private const
        CREATION_MESSAGE = "/root/discord_bot/listeners/creation/message/",
        IMPLEMENTATION_MESSAGE = "/root/discord_bot/listeners/implementation/message/",

        CREATION_MODAL = "/root/discord_bot/listeners/creation/modal/",
        IMPLEMENTATION_MODAL = "/root/discord_bot/listeners/implementation/modal/",

        CREATION_REACTION = "/root/discord_bot/listeners/creation/reaction/",
        IMPLEMENTATION_COMMAND = "/root/discord_bot/listeners/implementation/command/",

        IMPLEMENTATION_USER_TICKETS = "/root/discord_bot/listeners/custom/user_tickets/",
        IMPLEMENTATION_CHANNEL_COUNTING = "/root/discord_bot/listeners/custom/channel_counting/",
        IMPLEMENTATION_INVITE_TRACKER = "/root/discord_bot/listeners/custom/invite_tracker/",
        IMPLEMENTATION_USER_LEVELS = "/root/discord_bot/listeners/custom/user_level/",
        IMPLEMENTATION_CHANNEL_STATISTICS = "/root/discord_bot/listeners/custom/channel_statistics/",
        IMPLEMENTATION_REMINDER_MESSAGE = "/root/discord_bot/listeners/custom/reminder_message/",
        IMPLEMENTATION_STATUS_MESSAGE = "/root/discord_bot/listeners/custom/status_message/",
        IMPLEMENTATION_NOTIFICATION_MESSAGE = "/root/discord_bot/listeners/custom/notification_message/",
        IMPLEMENTATION_AI = "/root/discord_bot/listeners/custom/artificial_intelligence/";

    public function __construct(DiscordBot $bot)
    {
        $this->bot = $bot;
    }

    public function callMessageImplementation(Interaction    $interaction,
                                              MessageBuilder $messageBuilder,
                                              ?string        $class,
                                              ?string        $method,
                                              mixed          $objects = null): void
    {
        if ($class !== null && $method !== null) {
            require_once(self::IMPLEMENTATION_MESSAGE . $class . '.php');
            call_user_func_array(
                array($class, $method),
                array($this->bot, $interaction, $messageBuilder, $objects)
            );
        }
    }

    public function callModalImplementation(Interaction $interaction,
                                            ?string     $class,
                                            ?string     $method,
                                            mixed       $objects = null): void
    {
        if ($class !== null && $method !== null) {
            require_once(self::IMPLEMENTATION_MODAL . $class . '.php');
            call_user_func_array(
                array($class, $method),
                array($this->bot, $interaction, $objects)
            );
        }
    }

    public function callMessageBuilderCreation(?Interaction   $interaction,
                                               MessageBuilder $messageBuilder,
                                               ?string        $class,
                                               ?string        $method): MessageBuilder
    {
        if ($class !== null && $method !== null) {
            require_once(self::CREATION_MESSAGE . $class . '.php');
            return call_user_func_array(
                array($class, $method),
                array($this->bot, $interaction, $messageBuilder)
            );
        } else {
            return $messageBuilder;
        }
    }

    public function callModalCreation(Interaction $interaction,
                                      TextInput   $input,
                                      int         $position,
                                      ?string     $class,
                                      ?string     $method): TextInput
    {
        if ($class !== null && $method !== null) {
            require_once(self::CREATION_MODAL . $class . '.php');
            return call_user_func_array(
                array($class, $method),
                array($this->bot, $interaction, $input, $position)
            );
        } else {
            return $input;
        }
    }

    public function callCommandImplementation(object  $command,
                                              ?string $class,
                                              ?string $method): void
    {
        if ($class !== null && $method !== null) {
            require_once(self::IMPLEMENTATION_COMMAND . $class . '.php');
            try {
                $this->bot->discord->listenCommand(
                    $command->command_identification,
                    function (Interaction $interaction) use ($class, $method, $command) {
                        $mute = $this->bot->mute->isMuted($interaction->member, $interaction->channel, DiscordMute::COMMAND);

                        if ($mute !== null) {
                            $this->bot->utilities->acknowledgeCommandMessage(
                                $interaction,
                                MessageBuilder::new()->setContent($mute->creation_reason),
                                $command->ephemeral !== null
                            );
                        } else if ($command->required_permission !== null
                            && !$this->bot->permissions->hasPermission(
                                $interaction->member,
                                $command->required_permission
                            )) {
                            $this->bot->utilities->acknowledgeCommandMessage(
                                $interaction,
                                MessageBuilder::new()->setContent($command->no_permission_message),
                                $command->ephemeral !== null
                            );
                        } else if ($command->command_reply !== null) {
                            $this->bot->utilities->acknowledgeCommandMessage(
                                $interaction,
                                MessageBuilder::new()->setContent($command->command_reply),
                                $command->ephemeral !== null
                            );
                        } else {
                            call_user_func_array(
                                array($class, $method),
                                array($this->bot, $interaction, $command)
                            );
                        }
                    }
                );
            } catch (Throwable $ignored) {
            }
        }
    }

    public function callTicketImplementation(Interaction $interaction,
                                             ?string     $class,
                                             ?string     $method,
                                             mixed       $objects): void
    {
        if ($class !== null && $method !== null) {
            require_once(self::IMPLEMENTATION_USER_TICKETS . $class . '.php');
            call_user_func_array(
                array($class, $method),
                array($this->bot, $interaction, $objects)
            );
        }
    }

    public function callCountingGoalImplementation(?string $class,
                                                   ?string $method,
                                                   Message $message,
                                                   mixed   $object): void
    {
        if ($class !== null && $method !== null) {
            require_once(self::IMPLEMENTATION_CHANNEL_COUNTING . $class . '.php');
            call_user_func_array(
                array($class, $method),
                array($this->bot, $message, $object)
            );
        }
    }

    public function callInviteTrackerImplementation(?string $class,
                                                    ?string $method,
                                                    Invite  $invite): void
    {
        if ($class !== null && $method !== null) {
            require_once(self::IMPLEMENTATION_INVITE_TRACKER . $class . '.php');
            call_user_func_array(
                array($class, $method),
                array($this->bot, $invite)
            );
        }
    }

    public function callUserLevelsImplementation(?string $class,
                                                 ?string $method,
                                                 Channel $channel,
                                                 object  $configuration,
                                                 object  $oldTier,
                                                 object  $newTier): void
    {
        if ($class !== null && $method !== null) {
            require_once(self::IMPLEMENTATION_USER_LEVELS . $class . '.php');
            call_user_func_array(
                array($class, $method),
                array($this->bot, $channel, $configuration, $oldTier, $newTier)
            );
        }
    }

    public function callChannelStatisticsImplementation(?string  $class,
                                                        ?string  $method,
                                                        Guild    $guild,
                                                        ?Channel $channel,
                                                        string   $name,
                                                        object   $object): string
    {
        if ($class !== null && $method !== null) {
            require_once(self::IMPLEMENTATION_CHANNEL_STATISTICS . $class . '.php');
            return call_user_func_array(
                array($class, $method),
                array($this->bot, $guild, $channel, $name, $object)
            );
        }
        return $name;
    }

    public function callReminderMessageImplementation(?string        $class,
                                                      ?string        $method,
                                                      Channel|Thread $channel,
                                                      MessageBuilder $messageBuilder,
                                                      object         $object): MessageBuilder
    {
        if ($class !== null && $method !== null) {
            require_once(self::IMPLEMENTATION_REMINDER_MESSAGE . $class . '.php');
            return call_user_func_array(
                array($class, $method),
                array($this->bot, $channel, $messageBuilder, $object)
            );
        }
        return $messageBuilder;
    }

    public function callStatusMessageImplementation(?string        $class,
                                                    ?string        $method,
                                                    Channel        $channel,
                                                    Member         $member,
                                                    MessageBuilder $messageBuilder,
                                                    object         $object,
                                                    int            $case): MessageBuilder
    {
        if ($class !== null && $method !== null) {
            require_once(self::IMPLEMENTATION_STATUS_MESSAGE . $class . '.php');
            return call_user_func_array(
                array($class, $method),
                array($this->bot, $channel, $member, $messageBuilder, $object, $case)
            );
        }
        return $messageBuilder;
    }

    public function callNotificationMessageImplementation(MessageBuilder $message,
                                                          ?string        $class,
                                                          ?string        $method,
                                                          object         $object): MessageBuilder
    {
        if ($class !== null && $method !== null) {
            require_once(self::IMPLEMENTATION_NOTIFICATION_MESSAGE . $class . '.php');
            return call_user_func_array(
                array($class, $method),
                array($this->bot, $message, $object)
            );
        } else {
            return $message;
        }
    }

    public function callReactionCreation(MessageReaction $reaction,
                                         ?string         $class,
                                         ?string         $method): void
    {
        if ($class !== null && $method !== null) {
            require_once(self::CREATION_REACTION . $class . '.php');
            call_user_func_array(
                array($class, $method),
                array($this->bot, $reaction)
            );
        }
    }

    public function callAiTextImplementation(?string $class,
                                             ?string $method,
                                             object  $model,
                                             Message $originalMessage,
                                             object  $channel,
                                             ?array  $localInstructions,
                                             ?array  $publicInstructions): array
    {
        if ($class !== null && $method !== null) {
            require_once(self::IMPLEMENTATION_AI . $class . '.php');
            return call_user_func_array(
                array($class, $method),
                array($model, $this->bot, $originalMessage, $channel, $localInstructions, $publicInstructions)
            );
        } else {
            return array($localInstructions, $publicInstructions);
        }
    }
}