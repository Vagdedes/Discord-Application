<?php

class DiscordLimits
{
    private DiscordPlan $plan;
    private array $messageLimits, $costLimits, $messageCounter;

    public function __construct(DiscordPlan $plan)
    {
        $this->plan = $plan;
        $this->messageCounter = array();
        $this->messageLimits = get_sql_query(
            BotDatabaseTable::BOT_MESSAGE_LIMITS,
            null,
            array(
                array("deletion_date", null),
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
        $this->costLimits = get_sql_query(
            BotDatabaseTable::BOT_COST_LIMITS,
            null,
            array(
                array("deletion_date", null),
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

    public function isLimited($serverID, $channelID, $userID): array
    {
        $array = array();

        if (!empty($this->messageLimits)) {
            foreach ($this->messageLimits as $limit) {
                if (($limit->server_id === null || $limit->server_id === $serverID)
                    && ($limit->channel_id === null || $limit->channel_id === $channelID)) {
                    $loopUserID = $limit->user !== null ? $userID : null;
                    $count = $this->plan->conversation->getMessageCount(
                        $limit->server_id,
                        $limit->channel_id,
                        $loopUserID,
                        $limit->past_lookup,
                    );
                    $hash = $this->hash($limit->server_id, $limit->channel_id, $loopUserID);

                    if (array_key_exists($hash, $this->messageCounter)) {
                        $this->messageCounter[$hash]++;
                        $count = $this->messageCounter[$hash];
                    } else {
                        $this->messageCounter[$hash] = $count;
                    }

                    if ($count >= $limit->limit) {
                        $array[] = $limit;
                    }
                }
            }
        }
        if (!empty($this->costLimits)) {
            foreach ($this->costLimits as $limit) {
                if (($limit->server_id === null || $limit->server_id === $serverID)
                    && ($limit->channel_id === null || $limit->channel_id === $channelID)
                    && $this->plan->conversation->getCost(
                        $limit->server_id,
                        $limit->channel_id,
                        $limit->user !== null ? $userID : null,
                        $limit->past_lookup
                    ) >= $limit->limit) {
                    $array[] = $limit;
                }
            }
        }
        return $array;
    }

    private function hash($serverID, $channelID, $userID): int
    {
        $string = "";

        if ($serverID !== null) {
            $string .= $serverID;
        }
        if ($channelID !== null) {
            $string .= $channelID;
        }
        if ($userID !== null) {
            $string .= $userID;
        }
        return string_to_integer($string, true);
    }
}