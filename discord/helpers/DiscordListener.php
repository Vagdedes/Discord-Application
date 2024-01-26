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
    private DiscordPlan $plan;
    private const
        CREATION_MESSAGE = "/root/discord_bot/listeners/creation/message/",
        CREATION_MODAL = "/root/discord_bot/listeners/creation/modal/",
        CREATION_REACTION = "/root/discord_bot/listeners/creation/reaction/",

        IMPLEMENTATION_MESSAGE = "/root/discord_bot/listeners/implementation/message/",
        IMPLEMENTATION_MODAL = "/root/discord_bot/listeners/implementation/modal/",
        IMPLEMENTATION_COMMAND = "/root/discord_bot/listeners/implementation/command/",

        IMPLEMENTATION_USER_TICKETS = "/root/discord_bot/listeners/custom/user_tickets/",
        IMPLEMENTATION_CHANNEL_COUNTING = "/root/discord_bot/listeners/custom/channel_counting/",
        IMPLEMENTATION_INVITE_TRACKER = "/root/discord_bot/listeners/custom/invite_tracker/",
        IMPLEMENTATION_USER_LEVELS = "/root/discord_bot/listeners/custom/user_level/",
        IMPLEMENTATION_CHANNEL_STATISTICS = "/root/discord_bot/listeners/custom/channel_statistics/",
        IMPLEMENTATION_REMINDER_MESSAGE = "/root/discord_bot/listeners/custom/reminder_message/",
        IMPLEMENTATION_STATUS_MESSAGE = "/root/discord_bot/listeners/custom/status_message/",
        IMPLEMENTATION_CUSTOM_MUTE = "/root/discord_bot/listeners/custom/custom_mute/",
        IMPLEMENTATION_CUSTOM_LOGS = "/root/discord_bot/listeners/custom/custom_logs/",
        IMPLEMENTATION_NOTIFICATION_MESSAGE = "/root/discord_bot/listeners/custom/notification_message/",
        IMPLEMENTATION_AI_TEXT = "/root/discord_bot/listeners/custom/ai_text/",
        IMPLEMENTATION_AI_IMAGE = "/root/discord_bot/listeners/custom/ai_image/",
        IMPLEMENTATION_SOCIAL_ALERTS = "/root/discord_bot/listeners/custom/social_alerts/",
        IMPLEMENTATION_CHANNEL_OBJECTIVE = "/root/discord_bot/listeners/custom/user_objective/",
        IMPLEMENTATION_USER_NOTES = "/root/discord_bot/listeners/custom/user_notes/",
        IMPLEMENTATION_USER_TARGETS = "/root/discord_bot/listeners/custom/user_targets/",
        IMPLEMENTATION_REACTION_ROLES = "/root/discord_bot/listeners/custom/reaction_roles/",
        IMPLEMENTATION_CHANNEL_TEMPORARY = "/root/discord_bot/listeners/custom/channel_temporary/",
        IMPLEMENTATION_MESSAGE_TRANSFER = "/root/discord_bot/listeners/custom/transfer_message/",
        IMPLEMENTATION_MESSAGE_FILTER = "/root/discord_bot/listeners/custom/filter_message/",
        IMPLEMENTATION_MESSAGE_ANTI_EXPIRATION_THREAD = "/root/discord_bot/listeners/custom/anti_expiration_thread/",
        IMPLEMENTATION_USER_QUESTIONNAIRE = "/root/discord_bot/listeners/custom/user_questionnaires/",
        IMPLEMENTATION_USER_GIVEAWAYS = "/root/discord_bot/listeners/custom/user_giveaways/",
        IMPLEMENTATION_USER_POLLS = "/root/discord_bot/listeners/custom/user_polls/",
        IMPLEMENTATION_MESSAGE_OBJECTIVE = "/root/discord_bot/listeners/custom/objective_message/";

    public function __construct(DiscordPlan $plan)
    {
        $this->plan = $plan;
    }

    public function callMessageImplementation(Interaction    $interaction,
                                              MessageBuilder $messageBuilder,
                                              ?string        $class, ?string $method,
                                              mixed          $objects = null): void
    {
        if ($class !== null && $method !== null) {
            require_once(self::IMPLEMENTATION_MESSAGE . $this->plan->planID . "/" . $class . '.php');
            call_user_func_array(
                array($class, $method),
                array($this->plan, $interaction, $messageBuilder, $objects)
            );
        }
    }

    public function callModalImplementation(Interaction $interaction,
                                            ?string     $class, ?string $method,
                                            mixed       $objects = null): void
    {
        if ($class !== null && $method !== null) {
            require_once(self::IMPLEMENTATION_MODAL . $this->plan->planID . "/" . $class . '.php');
            call_user_func_array(
                array($class, $method),
                array($this->plan, $interaction, $objects)
            );
        }
    }

    public function callMessageBuilderCreation(?Interaction   $interaction,
                                               MessageBuilder $messageBuilder,
                                               ?string        $class, ?string $method): MessageBuilder
    {
        if ($class !== null && $method !== null) {
            require_once(self::CREATION_MESSAGE . $this->plan->planID . "/" . $class . '.php');
            return call_user_func_array(
                array($class, $method),
                array($this->plan, $interaction, $messageBuilder)
            );
        } else {
            return $messageBuilder;
        }
    }

    public function callModalCreation(Interaction $interaction,
                                      TextInput   $input,
                                      int         $position,
                                      ?string     $class, ?string $method): TextInput
    {
        if ($class !== null && $method !== null) {
            require_once(self::CREATION_MODAL . $this->plan->planID . "/" . $class . '.php');
            return call_user_func_array(
                array($class, $method),
                array($this->plan, $interaction, $input, $position)
            );
        } else {
            return $input;
        }
    }

    public function callCommandImplementation(object  $command,
                                              ?string $class, ?string $method): void
    {
        if ($class !== null && $method !== null) {
            require_once(
                self::IMPLEMENTATION_COMMAND
                . (empty($command->plan_id) ? "0" : $command->plan_id)
                . "/" . $class . '.php'
            );
            try {
                $this->plan->bot->discord->listenCommand(
                    $command->command_identification,
                    function (Interaction $interaction) use ($class, $method, $command) {
                        $mute = $this->plan->bot->mute->isMuted($interaction->member, $interaction->channel, DiscordMute::COMMAND);

                        if ($mute !== null) {
                            return $mute->creation_reason;
                        } else if ($command->required_permission !== null
                            && !$this->plan->permissions->hasPermission(
                                $interaction->member,
                                $command->required_permission
                            )) {
                            $this->plan->utilities->acknowledgeCommandMessage(
                                $interaction,
                                MessageBuilder::new()->setContent($command->no_permission_message),
                                $command->ephemeral !== null
                            );
                        } else if ($command->command_reply !== null) {
                            $this->plan->utilities->acknowledgeCommandMessage(
                                $interaction,
                                MessageBuilder::new()->setContent($command->command_reply),
                                $command->ephemeral !== null
                            );
                        } else {
                            call_user_func_array(
                                array($class, $method),
                                array($this->plan, $interaction, $command)
                            );
                        }
                    }
                );
            } catch (Throwable $ignored) {
            }
        }
    }

    public function callTicketImplementation(Interaction $interaction,
                                             ?string     $class, ?string $method,
                                             mixed       $objects): void
    {
        if ($class !== null && $method !== null) {
            require_once(self::IMPLEMENTATION_USER_TICKETS . $this->plan->planID . "/" . $class . '.php');
            call_user_func_array(
                array($class, $method),
                array($this->plan, $interaction, $objects)
            );
        }
    }

    public function callCountingGoalImplementation(?string $class, ?string $method,
                                                   Message $message,
                                                   mixed   $object): void
    {
        if ($class !== null && $method !== null) {
            require_once(self::IMPLEMENTATION_CHANNEL_COUNTING . $this->plan->planID . "/" . $class . '.php');
            call_user_func_array(
                array($class, $method),
                array($this->plan, $message, $object)
            );
        }
    }

    public function callInviteTrackerImplementation(?string $class, ?string $method,
                                                    Invite  $invite): void
    {
        if ($class !== null && $method !== null) {
            require_once(self::IMPLEMENTATION_INVITE_TRACKER . $this->plan->planID . "/" . $class . '.php');
            call_user_func_array(
                array($class, $method),
                array($this->plan, $invite)
            );
        }
    }

    public function callUserLevelsImplementation(?string $class, ?string $method,
                                                 Channel $channel,
                                                 object  $configuration,
                                                 object  $oldTier,
                                                 object  $newTier): void
    {
        if ($class !== null && $method !== null) {
            require_once(self::IMPLEMENTATION_USER_LEVELS . $this->plan->planID . "/" . $class . '.php');
            call_user_func_array(
                array($class, $method),
                array($this->plan, $channel, $configuration, $oldTier, $newTier)
            );
        }
    }

    public function callChannelStatisticsImplementation(?string  $class, ?string $method,
                                                        Guild    $guild,
                                                        ?Channel $channel,
                                                        string   $name,
                                                        object   $object): string
    {
        if ($class !== null && $method !== null) {
            require_once(self::IMPLEMENTATION_CHANNEL_STATISTICS . $this->plan->planID . "/" . $class . '.php');
            return call_user_func_array(
                array($class, $method),
                array($this->plan, $guild, $channel, $name, $object)
            );
        }
        return $name;
    }

    public function callReminderMessageImplementation(?string        $class, ?string $method,
                                                      Channel|Thread $channel,
                                                      MessageBuilder $messageBuilder,
                                                      object         $object): MessageBuilder
    {
        if ($class !== null && $method !== null) {
            require_once(self::IMPLEMENTATION_REMINDER_MESSAGE . $this->plan->planID . "/" . $class . '.php');
            return call_user_func_array(
                array($class, $method),
                array($this->plan, $channel, $messageBuilder, $object)
            );
        }
        return $messageBuilder;
    }

    public function callStatusMessageImplementation(?string        $class, ?string $method,
                                                    Channel        $channel,
                                                    Member         $member,
                                                    MessageBuilder $messageBuilder,
                                                    object         $object,
                                                    int            $case): MessageBuilder
    {
        if ($class !== null && $method !== null) {
            require_once(self::IMPLEMENTATION_STATUS_MESSAGE . $this->plan->planID . "/" . $class . '.php');
            return call_user_func_array(
                array($class, $method),
                array($this->plan, $channel, $member, $messageBuilder, $object, $case)
            );
        }
        return $messageBuilder;
    }

    public function callNotificationMessageImplementation(string  $message,
                                                          ?string $class, ?string $method,
                                                          object  $object): string
    {
        if ($class !== null && $method !== null) {
            require_once(self::IMPLEMENTATION_NOTIFICATION_MESSAGE . $this->plan->planID . "/" . $class . '.php');
            return call_user_func_array(
                array($class, $method),
                array($this->plan, $message, $object)
            );
        } else {
            return $message;
        }
    }

    public function callReactionCreation(MessageReaction $reaction,
                                         ?string         $class, ?string $method): void
    {
        if ($class !== null && $method !== null) {
            require_once(self::CREATION_REACTION . $this->plan->planID . "/" . $class . '.php');
            call_user_func_array(
                array($class, $method),
                array($this->plan, $reaction)
            );
        }
    }
}