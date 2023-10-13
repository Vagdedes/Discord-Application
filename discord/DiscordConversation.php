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
                array("plan_id", $this->plan->planID),
                array("user_id", $userID),
                array("deletion_date", null),
            ),
            array(
                "DESC",
                "id"
            ),
            $limit
        );

        if (!$object) {
            foreach ($array as $arrayKey => $row) {
                $array[$arrayKey] = $row->message_content;
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
                array("plan_id", $this->plan->planID),
                array("user_id", $userID),
                array("deletion_date", null),
            ),
            array(
                "DESC",
                "id"
            ),
            $limit
        );

        if (!$object) {
            foreach ($array as $arrayKey => $row) {
                $array[$arrayKey] = $row->message_content;
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
            foreach ($messages as $row) {
                $final[strtotime($row->creation_date)] = "user: " . $row;
            }
        }
        if (!empty($replies)) {
            foreach ($replies as $row) {
                $final[strtotime($row->creation_date)] = "bot: " . $row;
            }
        }
        krsort($final);
        return $final;
    }

    // Separator

    public function addReply($botID, $serverID, $channelID, $threadID,
                             $userID, $messageID, $message): void
    {
        global $scheduler;
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