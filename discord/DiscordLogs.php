<?php

class DiscordLogs
{

    private ?int $botID;

    public function __construct($botID)
    {
        $this->botID = $botID;
    }

    public function logInfo($userID, ?string $action, $object, $oldObject = null): void
    {
        sql_insert(
            BotDatabaseTable::BOT_LOGS,
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

    public function logError($planID, $object, $exit = false): void
    {
        sql_insert(
            BotDatabaseTable::BOT_ERRORS,
            array(
                "bot_id" => $this->botID,
                "plan_id" => $planID,
                "object" => $object !== null ? json_encode($object) : null,
                "creation_date" => get_current_date()
            )
        );
        if ($exit) {
            $exit($object);
        } else {
            var_dump($object);
        }
    }
}