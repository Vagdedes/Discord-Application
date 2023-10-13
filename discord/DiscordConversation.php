<?php

class DiscordConversation
{
    private DiscordPlan $plan;

    public function __construct(DiscordPlan $plan)
    {
        $this->plan = $plan;
    }

    public function getMessages($userID, ?int $limit = 0): array
    {
        set_sql_cache("1 second");
        return get_sql_query(
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
    }

    public function getReplies($userID, ?int $limit = 0): array
    {
        set_sql_cache("1 second");
        return get_sql_query(
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
    }

    public function getConversation($userID, ?int $limit = 0): array
    {
        $final = array();
        $messages = $this->getMessages($userID, $limit);
        $replies = $this->getReplies($userID, $limit);

        if (!empty($messages)) {
            foreach ($messages as $row) {
                $final[strtotime($row->creation_date)] = $row;
            }
        }
        if (!empty($replies)) {
            foreach ($replies as $row) {
                $final[strtotime($row->creation_date)] = $row;
            }
        }
        krsort($final);
        return $final;
    }

    // Separator

    public function addReply($botID, $serverID, $channelID, $userID, $messageID, $message): void
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
                    "user_id" => $userID,
                    "message_id" => $messageID,
                    "message_content" => $message,
                    "creation_date" => get_current_date(),
                )
            )
        );
    }

    public function addMessage($botID, $serverID, $channelID, $userID, $messageID, $message): void
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
                    "user_id" => $userID,
                    "message_id" => $messageID,
                    "message_content" => $message,
                    "creation_date" => get_current_date(),
                )
            )
        );
    }
}