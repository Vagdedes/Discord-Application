<?php

class DiscordLogs
{

    private int $botID;

    public function __construct($botID)
    {
        $this->botID = $botID;
    }

    public function log($userID, ?string $action, $object, $oldObject = null): void
    {
        sql_insert(
            DatabaseVariables::BOT_LOGS_TABLE,
            array(
                "bot_id" => $this->botID,
                "user_id" => $userID,
                "action" => $action,
                "object" => $object !== null ? json_encode($object) : null,
                "old_object" => $oldObject !== null ? json_encode($object) : null,
                "creation_date" => get_current_date()
            )
        );
    }
}