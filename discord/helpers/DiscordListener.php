<?php

use Discord\Builders\MessageBuilder;
use Discord\Parts\Channel\Invite;
use Discord\Parts\Channel\Message;
use Discord\Parts\Interactions\Interaction;

class DiscordListener
{
    private DiscordPlan $plan;
    private const
        IMPLEMENTATION_MESSAGE = "/root/discord_bot/listeners/implementation/message/",
        IMPLEMENTATION_MODAL = "/root/discord_bot/listeners/implementation/modal/",
        CREATION_MESSAGE = "/root/discord_bot/listeners/creation/message/",
        CREATION_MODAL = "/root/discord_bot/listeners/creation/modal/",
        IMPLEMENTATION_COMMAND = "/root/discord_bot/listeners/implementation/command/",
        IMPLEMENTATION_TICKET = "/root/discord_bot/listeners/implementation/ticket/",
        IMPLEMENTATION_COUNTING_GOAL = "/root/discord_bot/listeners/implementation/counting_goal/",
        IMPLEMENTATION_INVITE_TRACKER = "/root/discord_bot/listeners/implementation/invite_tracker/";

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
            $this->plan->bot->processing++;
            require_once(self::IMPLEMENTATION_MESSAGE . $this->plan->planID . "/" . $class . '.php');
            call_user_func_array(
                array($class, $method),
                array($this->plan, $interaction, $messageBuilder, $objects)
            );
            $this->plan->bot->processing--;
        }
    }

    public function callModalImplementation(Interaction $interaction,
                                            ?string     $class, ?string $method,
                                            mixed       $objects = null): void
    {
        if ($class !== null && $method !== null) {
            $this->plan->bot->processing++;
            require_once(self::IMPLEMENTATION_MODAL . $this->plan->planID . "/" . $class . '.php');
            call_user_func_array(
                array($class, $method),
                array($this->plan, $interaction, $objects)
            );
            $this->plan->bot->processing--;
        }
    }

    public function callMessageBuilderCreation(?Interaction   $interaction,
                                               MessageBuilder $messageBuilder,
                                               ?string        $class, ?string $method): MessageBuilder
    {
        if ($class !== null && $method !== null) {
            $this->plan->bot->processing++;
            require_once(self::CREATION_MESSAGE . $this->plan->planID . "/" . $class . '.php');
            $outcome = call_user_func_array(
                array($class, $method),
                array($this->plan, $interaction, $messageBuilder)
            );
            $this->plan->bot->processing--;
            return $outcome;
        } else {
            return $messageBuilder;
        }
    }

    public function callModalCreation(Interaction $interaction,
                                      array       $actionRows,
                                      ?string     $class, ?string $method): array
    {
        if ($class !== null && $method !== null) {
            $this->plan->bot->processing++;
            require_once(self::CREATION_MODAL . $this->plan->planID . "/" . $class . '.php');
            $outcome = call_user_func_array(
                array($class, $method),
                array($this->plan, $interaction, $actionRows)
            );
            $this->plan->bot->processing--;
            return $outcome;
        } else {
            return $actionRows;
        }
    }

    public function callCommandImplementation(object  $command,
                                              ?string $class, ?string $method): void
    {
        if ($class !== null && $method !== null) {
            $this->plan->bot->processing++;
            require_once(
                self::IMPLEMENTATION_COMMAND
                . (empty($command->plan_id) ? "0" : $command->plan_id)
                . "/" . $class . '.php'
            );
            try {
                $this->plan->discord->listenCommand(
                    $command->command_identification,
                    function (Interaction $interaction) use ($class, $method, $command) {
                        if ($command->required_permission !== null
                            && !$this->plan->permissions->hasPermission(
                                $interaction->member,
                                $command->required_permission
                            )) {
                            $this->plan->utilities->acknowledgeMessage(
                                $interaction,
                                MessageBuilder::new()->setContent($command->no_permission_message),
                                $command->ephemeral !== null
                            );
                        } else if ($command->command_reply !== null) {
                            $this->plan->utilities->acknowledgeMessage(
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
            $this->plan->bot->processing--;
        }
    }

    public function callTicketImplementation(Interaction $interaction,
                                             ?string     $class, ?string $method,
                                             mixed       $objects): void
    {
        if ($class !== null && $method !== null) {
            $this->plan->bot->processing++;
            require_once(self::IMPLEMENTATION_TICKET . $this->plan->planID . "/" . $class . '.php');
            call_user_func_array(
                array($class, $method),
                array($this->plan, $interaction, $objects)
            );
            $this->plan->bot->processing--;
        }
    }

    public function callCountingGoalImplementation(?string $class, ?string $method,
                                                   Message $message,
                                                   mixed   $object): void
    {
        if ($class !== null && $method !== null) {
            $this->plan->bot->processing++;
            require_once(self::IMPLEMENTATION_COUNTING_GOAL . $this->plan->planID . "/" . $class . '.php');
            call_user_func_array(
                array($class, $method),
                array($this->plan, $message, $object)
            );
            $this->plan->bot->processing--;
        }
    }

    public function callInviteTrackerImplementation(?string $class, ?string $method,
                                                    Invite  $invite,
                                                    mixed   $object): void
    {
        if ($class !== null && $method !== null) {
            $this->plan->bot->processing++;
            require_once(self::IMPLEMENTATION_INVITE_TRACKER . $this->plan->planID . "/" . $class . '.php');
            call_user_func_array(
                array($class, $method),
                array($this->plan, $invite, $object)
            );
            $this->plan->bot->processing--;
        }
    }
}