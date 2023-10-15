<?php

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
        clear_memory(array(self::class), true);
    }

    public function process($serverID, $channelID, $userID, $messageContent): ?string
    {
        if (!empty($this->staticCommands)) {
            $cacheKey = array(__METHOD__, $this->plan->planID, $serverID, $channelID, $userID, $messageContent);
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
                        && $messageContent == ($command->command_placeholder . $command->command_identification)) {
                        $reply = $command->command_reply;
                        set_key_value_pair($cacheKey, array($command, $reply));
                        $this->getCooldown($serverID, $channelID, $userID, $cache[0]);
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
                    && starts_with($messageContent, $command->command_placeholder . $command->command_identification)) {
                    switch ($command->command_identification) {
                        default:
                            break;
                    }
                    break;
                }
            }
        }
        return null;
    }

    private function getCooldown($serverID, $channelID, $userID, $command): array
    {
        if ($command->cooldown_duration !== null) {
            $cacheKey = array(
                __METHOD__, $this->plan->planID, $serverID, $channelID, $userID,
                $command->command_placeholder . $command->command_identification);
            $cache = get_key_value_pair($cacheKey);

            if ($cache !== null) {
                return array(true, $command->cooldown_message);
            } else {
                set_key_value_pair($cacheKey, true, 5);
                return array(false, null);
            }
        } else {
            return array(false, null);
        }
    }
}