<?php

class DiscordConversation
{
    private DiscordPlan $plan;

    public function __construct(DiscordPlan $plan)
    {
        $this->plan = $plan;
    }

    public function getMessages(int|string $userID, ?int $limit = 0, bool $object = true): array
    {
        set_sql_cache("1 second");
        $array = get_sql_query(
            BotDatabaseTable::BOT_MESSAGES,
            array("creation_date", "message_content"),
            array(
                array("user_id", $userID),
                array("deletion_date", null),
                null,
                array("plan_id", "IS", null, 0),
                array("plan_id", $this->plan->planID),
                null,
            ),
            array(
                "DESC",
                "id"
            ),
            $limit
        );

        if (!$object) {
            foreach ($array as $arrayKey => $row) {
                unset($array[$arrayKey]);
                $array[strtotime($row->creation_date)] = $row->message_content;
            }
            krsort($array);
        }
        return $array;
    }

    public function getReplies(int|string $userID, ?int $limit = 0, bool $object = true): array
    {
        set_sql_cache("1 second");
        $array = get_sql_query(
            BotDatabaseTable::BOT_REPLIES,
            array("creation_date", "message_content"),
            array(
                array("user_id", $userID),
                array("deletion_date", null),
                null,
                array("plan_id", "IS", null, 0),
                array("plan_id", $this->plan->planID),
                null,
            ),
            array(
                "DESC",
                "id"
            ),
            $limit
        );

        if (!$object) {
            foreach ($array as $arrayKey => $row) {
                unset($array[$arrayKey]);
                $array[strtotime($row->creation_date)] = $row->message_content;
            }
            krsort($array);
        }
        return $array;
    }

    public function getConversation(int|string $userID, ?int $limit = 0, bool $object = true): array
    {
        $final = array();
        $messages = $this->getMessages($userID, $limit, $object);
        $replies = $this->getReplies($userID, $limit, $object);

        if (!empty($messages)) {
            if ($object) {
                foreach ($messages as $row) {
                    $row->user = true;
                    $final[strtotime($row->creation_date)] = $row;
                }
            } else {
                foreach ($messages as $arrayKey => $row) {
                    $final[$arrayKey] = "user: " . $row;
                }
            }
        }
        if (!empty($replies)) {
            if ($object) {
                foreach ($replies as $row) {
                    $row->user = false;
                    $final[strtotime($row->creation_date)] = $row;
                }
            } else {
                foreach ($messages as $arrayKey => $row) {
                    $final[$arrayKey] = "bot (you): " . $row;
                }
            }
        }
        krsort($final);
        return $final;
    }

    // Separator

    public function getCost(int|string|null $serverID, int|string|null $channelID, int|string|null $userID,
                            int|string      $pastLookup): float
    {
        $cacheKey = array(__METHOD__, $this->plan->planID, $serverID, $channelID, $userID, $pastLookup);
        $cache = get_key_value_pair($cacheKey);

        if ($cache !== null) {
            return $cache;
        } else {
            $cost = 0.0;
            $array = get_sql_query(
                BotDatabaseTable::BOT_REPLIES,
                array("cost"),
                array(
                    $serverID !== null ? array("server_id", $serverID) : "",
                    $channelID !== null ? array("channel_id", $channelID) : "",
                    $userID !== null ? array("user_id", $userID) : "",
                    array("deletion_date", null),
                    array("creation_date", ">", get_past_date($pastLookup)),
                    null,
                    array("plan_id", "IS", null, 0),
                    array("plan_id", $this->plan->planID),
                    null,
                ),
                array(
                    "DESC",
                    "id"
                )
            );

            foreach ($array as $row) {
                $cost += $row->cost;
            }
            set_key_value_pair($cacheKey, $cost, $pastLookup);
            return $cost;
        }
    }

    public function getMessageCount(int|string|null $serverID, int|string|null $channelID,
                                    int|string|null $userID, int|string $pastLookup): float
    {
        $cacheKey = array(__METHOD__, $this->plan->planID, $serverID, $channelID, $userID, $pastLookup);
        $cache = get_key_value_pair($cacheKey);

        if ($cache !== null) {
            return $cache;
        } else {
            $array = get_sql_query(
                BotDatabaseTable::BOT_REPLIES,
                array("id"),
                array(
                    $serverID !== null ? array("server_id", $serverID) : "",
                    $channelID !== null ? array("channel_id", $channelID) : "",
                    $userID !== null ? array("user_id", $userID) : "",
                    array("deletion_date", null),
                    array("creation_date", ">", get_past_date($pastLookup)),
                    null,
                    array("plan_id", "IS", null, 0),
                    array("plan_id", $this->plan->planID),
                    null,
                ),
                array(
                    "DESC",
                    "id"
                )
            );
            $amount = sizeof($array);
            set_key_value_pair($cacheKey, $amount, $pastLookup);
            return $amount;
        }
    }

    // Separator

    public function addReply(int|string      $serverID, int|string $channelID,
                             int|string|null $threadID,
                             int|string      $userID,
                             int|string      $messageID, string $messageContent,
                             int|float       $cost, string $currencyCode): void
    {
        $currency = new DiscordCurrency($currencyCode);
        sql_insert(
            BotDatabaseTable::BOT_REPLIES,
            array(
                "plan_id" => $this->plan->planID,
                "bot_id" => $this->plan->botID,
                "server_id" => $serverID,
                "channel_id" => $channelID,
                "thread_id" => $threadID,
                "user_id" => $userID,
                "message_id" => $messageID,
                "message_content" => $messageContent,
                "cost" => $cost,
                "currency_id" => $currency->exists ? $currency->id : null,
                "creation_date" => get_current_date(),
            )
        );
    }

    public function addMessage(int|string      $serverID, int|string $channelID,
                               int|string|null $threadID,
                               int|string      $userID,
                               int|string      $messageID, string $messageContent): void
    {
        sql_insert(
            BotDatabaseTable::BOT_MESSAGES,
            array(
                "plan_id" => $this->plan->planID,
                "bot_id" => $this->plan->botID,
                "server_id" => $serverID,
                "channel_id" => $channelID,
                "thread_id" => $threadID,
                "user_id" => $userID,
                "message_id" => $messageID,
                "message_content" => $messageContent,
                "creation_date" => get_current_date(),
            )
        );
    }
}