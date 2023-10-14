<?php

class DiscordConversation
{
    private DiscordPlan $plan;

    public function __construct(DiscordPlan $plan)
    {
        $this->plan = $plan;
    }

    public function getMessages($userID, ?int $limit = 0, $object = true): array
    {
        set_sql_cache("1 second");
        $array = get_sql_query(
            BotDatabaseTable::BOT_MESSAGES,
            null,
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
        }
        return $array;
    }

    public function getReplies($userID, ?int $limit = 0, $object = true): array
    {
        set_sql_cache("1 second");
        $array = get_sql_query(
            BotDatabaseTable::BOT_REPLIES,
            null,
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
        }
        return $array;
    }

    public function getConversation($userID, ?int $limit = 0, $object = true): array
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
                    $final[$arrayKey] = "user: " . $row;
                }
            }
        }
        krsort($final);
        return $final;
    }

    // Separator

    public function addReply($botID, $serverID, $channelID, $threadID,
                             $userID, $messageID, $message,
                             $cost, $currencyCode): void
    {
        global $scheduler;
        $currency = new DiscordCurrency($currencyCode);
        $scheduler->addTask(
            null,
            "sql_insert",
            array(
                BotDatabaseTable::BOT_REPLIES,
                array(
                    "plan_id" => $this->plan->planID,
                    "bot_id" => $botID,
                    "server_id" => $serverID,
                    "channel_id" => $channelID,
                    "thread_id" => $threadID,
                    "user_id" => $userID,
                    "message_id" => $messageID,
                    "message_content" => $message,
                    "cost" => $cost,
                    "currency_id" => $currency->exists ? $currency->id : null,
                    "creation_date" => get_current_date(),
                )
            )
        );
    }

    public function addMessage($botID, $serverID, $channelID, $threadID,
                               $userID, $messageID, $message): void
    {
        global $scheduler;
        $scheduler->addTask(
            null,
            "sql_insert",
            array(
                BotDatabaseTable::BOT_MESSAGES,
                array(
                    "plan_id" => $this->plan->planID,
                    "bot_id" => $botID,
                    "server_id" => $serverID,
                    "channel_id" => $channelID,
                    "thread_id" => $threadID,
                    "user_id" => $userID,
                    "message_id" => $messageID,
                    "message_content" => $message,
                    "creation_date" => get_current_date(),
                )
            )
        );
    }
}