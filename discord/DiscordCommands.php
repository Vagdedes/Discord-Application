<?php

use Discord\Builders\MessageBuilder;
use Discord\Parts\Channel\Message;

class DiscordCommands
{
    private DiscordPlan $plan;
    private array $staticCommands, $dynamicCommands;

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
                array("plan_id", $this->plan->planID),
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
                null,
                array("plan_id", "IS", null, 0),
                array("plan_id", $this->plan->planID),
                null,
                null,
                array("expiration_date", "IS", null, 0),
                array("expiration_date", ">", get_current_date()),
                null
            )
        );
    }

    public function process(Message    $message,
                            int|string $serverID, int|string $channelID, int|string $userID): string|null|MessageBuilder
    {
        if ($userID !== $this->plan->botID) {
            if (!empty($this->staticCommands)) {
                $cacheKey = array(__METHOD__, $this->plan->planID, $serverID, $channelID, $userID, $message->content);
                $cache = get_key_value_pair($cacheKey);

                if ($cache !== null) {
                    $cooldown = $this->getCooldown($serverID, $channelID, $userID, $cache[0]);

                    if ($cooldown[0]) {
                        return $cooldown[1];
                    } else {
                        return $cache[1];
                    }
                } else {
                    foreach ($this->staticCommands as $command) {
                        if (($command->server_id === null || $command->server_id == $serverID)
                            && ($command->channel_id === null || $command->channel_id == $channelID)
                            && ($command->user_id === null || $command->user_id == $userID)
                            && $message->content == ($command->command_placeholder . $command->command_identification)) {
                            $reply = $command->command_reply;
                            set_key_value_pair($cacheKey, array($command, $reply));
                            $this->getCooldown($serverID, $channelID, $userID, $command);
                            return $reply;
                        }
                    }
                }
            }
            if (!empty($this->dynamicCommands)) {
                foreach ($this->dynamicCommands as $command) {
                    if (($command->server_id === null || $command->server_id == $serverID)
                        && ($command->channel_id === null || $command->channel_id == $channelID)
                        && ($command->user_id === null || $command->user_id == $userID)
                        && starts_with($message->content, $command->command_placeholder . $command->command_identification)) {
                        if ($command->required_permission !== null
                            && !$this->plan->permissions->userHasPermission(
                                $userID,
                                $command->required_permission
                            )) {
                            return "You do not have permission to use this command.";
                        }
                        $arguments = explode($command->argument_separator ?? " ", $message->content);
                        unset($arguments[0]);
                        $argumentSize = sizeof($arguments);

                        switch ($command->command_identification) {
                            case "close-ticket":
                                $arguments = explode($command->argument_separator, $message->content);

                                if ($argumentSize === 0) {
                                    $close = $this->plan->ticket->closeByChannel($message->channel, $userID);

                                    if ($close !== null) {
                                        return "Ticket could not be closed: " . $close;
                                    }
                                } else {
                                    $ticketID = $arguments[1];

                                    if (!is_numeric($ticketID)) {
                                        return "Invalid ticket-id argument.";
                                    }
                                    $close = $this->plan->ticket->closeByID($ticketID, $userID);

                                    if ($close !== null) {
                                        return "Ticket could not be closed: " . $close;
                                    }
                                }
                                break;
                            case "get-tickets":
                                $arguments = explode($command->argument_separator, $message->content);

                                if ($argumentSize === 0) {
                                    return "Missing user argument.";
                                } else if ($argumentSize > 1) {
                                    return "Too many arguments.";
                                } else {
                                    $findUserID = $arguments[1];

                                    if (!is_numeric($findUserID)) {
                                        $findUserID = substr($findUserID, 2, -1);

                                        if (!is_numeric($findUserID)) {
                                            return "Invalid user argument.";
                                        }
                                    }
                                    $tickets = $this->plan->ticket->getMultiple(
                                        $findUserID,
                                        null,
                                        25,
                                        false
                                    );

                                    if (empty($tickets)) {
                                        return "No tickets found for user.";
                                    } else {
                                        return $this->plan->ticket->loadTicketsMessage($tickets);
                                    }
                                }
                            case "get-ticket":
                                $arguments = explode($command->argument_separator, $message->content);

                                if ($argumentSize === 0) {
                                    return "Missing ticket-id argument.";
                                } else if ($argumentSize > 1) {
                                    return "Too many arguments.";
                                } else {
                                    $ticketID = $arguments[1];

                                    if (!is_numeric($ticketID)) {
                                        return "Invalid ticket-id argument.";
                                    }
                                    $ticket = $this->plan->ticket->getSingle($ticketID);

                                    if ($ticket === null) {
                                        return "Ticket not found.";
                                    } else {
                                        return $this->plan->ticket->loadSingleTicketMessage($ticket);
                                    }
                                }
                            default:
                                break;
                        }
                        break;
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
                __METHOD__, $this->plan->planID, $serverID, $channelID, $userID,
                $command->command_placeholder . $command->command_identification);
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
}