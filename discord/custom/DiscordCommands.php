<?php

use Discord\Builders\CommandBuilder;
use Discord\Builders\MessageBuilder;
use Discord\Parts\Channel\Message;
use Discord\Parts\Interactions\Command\Option;
use Discord\Parts\User\Member;

class DiscordCommands
{
    private DiscordPlan $plan;
    public array $staticCommands, $dynamicCommands, $nativeCommands;

    public function __construct(DiscordPlan $plan)
    {
        $this->plan = $plan;
        $this->staticCommands = get_sql_query(
            BotDatabaseTable::BOT_COMMANDS,
            null,
            array(
                array("deletion_date", null),
                array("command_reply", "IS NOT", null),
                null,
                array("plan_id", "IS", null, 0),
                array("plan_id", $plan->planID),
                null,
                null,
                array("expiration_date", "IS", null, 0),
                array("expiration_date", ">", get_current_date()),
                null
            )
        );
        $this->dynamicCommands = get_sql_query(
            BotDatabaseTable::BOT_COMMANDS,
            null,
            array(
                array("deletion_date", null),
                array("command_reply", null),
                array("command_placeholder", "!=", "/"),
                null,
                array("plan_id", "IS", null, 0),
                array("plan_id", $plan->planID),
                null,
                null,
                array("expiration_date", "IS", null, 0),
                array("expiration_date", ">", get_current_date()),
                null
            )
        );
        $this->nativeCommands = get_sql_query(
            BotDatabaseTable::BOT_COMMANDS,
            null,
            array(
                array("deletion_date", null),
                array("command_placeholder", "/"),
                null,
                array("expiration_date", "IS", null, 0),
                array("expiration_date", ">", get_current_date()),
                null
            ),
            array(
                "DESC",
                "id"
            )
        );

        if (!empty($this->nativeCommands)) {
            foreach ($this->nativeCommands as $command) {
                if (has_memory_cooldown(self::class . "-" . $command->id)) {
                    continue;
                }
                $command->arguments = get_sql_query(
                    BotDatabaseTable::BOT_COMMAND_ARGUMENTS,
                    null,
                    array(
                        array("deletion_date", null),
                        array("command_id", $command->id)
                    ),
                    array(
                        "DESC",
                        "priority"
                    ),
                    DiscordInheritedLimits::MAX_ARGUMENTS_PER_COMMAND
                );
                $commandBuilder = CommandBuilder::new()
                    ->setName($command->command_identification)
                    ->setDescription($command->command_description);

                if (!empty($command->arguments)) {
                    global $logger;

                    foreach ($command->arguments as $argument) {
                        $option = new Option($this->plan->bot->discord);
                        $option->setName(
                            $argument->name
                        )->setRequired(
                            $argument->required !== null
                        );

                        switch ($argument->type) {
                            case "string":
                                $option->setType(Option::STRING);
                                break;
                            case "integer":
                                $option->setType(Option::INTEGER);
                                break;
                            case "boolean":
                                $option->setType(Option::BOOLEAN);
                                break;
                            case "user":
                                $option->setType(Option::USER);
                                break;
                            case "channel":
                                $option->setType(Option::CHANNEL);
                                break;
                            case "role":
                                $option->setType(Option::ROLE);
                                break;
                            case "mentionable":
                                $option->setType(Option::MENTIONABLE);
                                break;
                            case "double":
                                $option->setType(Option::NUMBER);
                                break;
                            case "sub-command":
                                $option->setType(Option::SUB_COMMAND);
                                break;
                            case "sub-command-group":
                                $option->setType(Option::SUB_COMMAND_GROUP);
                                break;
                            case "attachment":
                                $option->setType(Option::ATTACHMENT);
                                break;
                            default:
                                $logger->logError(
                                    $planCopy->planID,
                                    "Invalid argument '" . $argument->id . "' in command with ID: " . $command->id
                                );
                                break;
                        }
                        if ($argument->description !== null) {
                            $option->setDescription($argument->description);
                        }
                        if ($argument->min_value !== null) {
                            $option->setMinValue($argument->min_value);
                        }
                        if ($argument->max_value !== null) {
                            $option->setMaxValue($argument->max_value);
                        }
                        if ($argument->min_length !== null) {
                            $option->setMinLength($argument->min_length);
                        }
                        if ($argument->max_length !== null) {
                            $option->setMaxLength($argument->max_length);
                        }
                        $commandBuilder->addOption($option);
                    }
                }
                $this->plan->bot->discord->application->commands->save(
                    $this->plan->bot->discord->application->commands->create(
                        $commandBuilder->toArray()
                    )
                );
                $this->plan->listener->callCommandImplementation(
                    $command,
                    $command->listener_class,
                    $command->listener_method
                );
            }
        }
    }

    public function process(Message $message, Member $user): string|null|MessageBuilder
    {
        if ($user->id !== $this->plan->bot->botID) {
            if (!empty($this->staticCommands)) {
                $cacheKey = array(
                    __METHOD__,
                    $this->plan->planID,
                    $message->guild_id,
                    $message->channel_id,
                    $user->id,
                    $message->content
                );
                $cache = get_key_value_pair($cacheKey);

                if ($cache !== null) {
                    $mute = $this->plan->bot->mute->isMuted($user, $message->channel, DiscordMute::COMMAND);

                    if ($mute !== null) {
                        return $mute->creation_reason;
                    } else {
                        $cooldown = $this->getCooldown($message->guild_id, $message->channel_id, $user->id, $cache[0]);

                        if ($cooldown[0]) {
                            return $cooldown[1];
                        } else {
                            return $cache[1];
                        }
                    }
                } else {
                    foreach ($this->staticCommands as $command) {
                        if (($command->server_id === null || $command->server_id == $message->guild_id)
                            && ($command->channel_id === null || $command->channel_id == $message->channel_id)
                            && $message->content == ($command->command_placeholder . $command->command_identification)) {
                            $mute = $planCopy->bot->mute->isMuted($user, $message->channel, DiscordMute::COMMAND);

                            if ($mute !== null) {
                                return $mute->creation_reason;
                            } else if ($command->required_permission !== null
                                && !$this->plan->bot->permissions->hasPermission(
                                    $user,
                                    $command->required_permission
                                )) {
                                return $command->no_permission_message;
                            } else {
                                $reply = $command->command_reply;
                                set_key_value_pair($cacheKey, array($command, $reply));
                                $this->getCooldown($message->guild_id, $message->channel_id, $user->id, $command);
                                return $reply;
                            }
                        }
                    }
                }
            }
            if (!empty($this->dynamicCommands)) {
                foreach ($this->dynamicCommands as $command) {
                    if (($command->server_id === null || $command->server_id == $message->guild_id)
                        && ($command->channel_id === null || $command->channel_id == $message->channel_id)
                        && starts_with($message->content, $command->command_placeholder . $command->command_identification)) {
                        $mute = $this->plan->bot->mute->isMuted($user, $message->channel, DiscordMute::COMMAND);

                        if ($mute !== null) {
                            return $mute->creation_reason;
                        } else if ($command->required_permission !== null
                            && !$this->plan->bot->permissions->hasPermission(
                                $user,
                                $command->required_permission
                            )) {
                            return $command->no_permission_message;
                        } else if ($command->listener_class !== null && $command->listener_method !== null) {
                            call_user_func_array(
                                array($command->listener_class, $command->listener_method),
                                array($this->getPlan($command), $message, $command)
                            );
                        }
                    }
                }
            }
        }
        return null;
    }

    private function getCooldown(int|string $serverID, int|string $channelID, int|string $userID,
                                 object     $command): array
    {
        if ($command->cooldown_duration !== null) {
            $cacheKey = array(
                __METHOD__,
                $this->plan->planID,
                $serverID, $channelID,
                $userID,
                $command->command_placeholder . $command->command_identification
            );
            $cache = get_key_value_pair($cacheKey);

            if ($cache !== null) {
                return array(true, $command->cooldown_message);
            } else {
                set_key_value_pair($cacheKey, true, $command->cooldown_duration);
                return array(false, null);
            }
        } else {
            return array(false, null);
        }
    }

    public function getPlan(object $command): DiscordPlan
    {
        if ($command->plan_id !== null) {
            $planCopy = $this->plan->bot->getPlan($command->plan_id);

            if ($planCopy === null) {
                $planCopy = $this->plan;
            }
        } else {
            $planCopy = $this->plan;
        }
        return $planCopy;
    }
}