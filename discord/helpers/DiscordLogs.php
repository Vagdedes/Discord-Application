<?php

class DiscordLogs
{

    private ?DiscordBot $bot;

    public function __construct(?DiscordBot $bot)
    {
        $this->bot = $bot;
    }

    public function logInfo(int|string|null $userID, ?string $action, mixed $object, mixed $oldObject = null): void
    {
        check_clear_memory();
        sql_insert(
            BotDatabaseTable::BOT_LOGS,
            array(
                "bot_id" => $this->bot?->botID,
                "user_id" => $userID,
                "action" => $action,
                "object" => $object !== null ? json_encode($object) : null,
                "old_object" => $oldObject !== null ? json_encode($object) : null,
                "creation_date" => get_current_date()
            )
        );
        $this->bot?->refresh();
    }

    public function logError(int|string|null $planID, mixed $object, bool $exit = false): void
    {
        $hasBot = $this->bot !== null;

        if ($hasBot) {
            $this->bot->processing++;
        }
        sql_insert(
            BotDatabaseTable::BOT_ERRORS,
            array(
                "bot_id" => $this->bot?->botID,
                "plan_id" => $planID,
                "object" => $object !== null ? json_encode($object) : null,
                "creation_date" => get_current_date()
            )
        );
        if ($exit) {
            exit($object);
        } else {
            var_dump($object);
        }
        if ($hasBot) {
            $this->bot->processing--;
        }
    }
    //todo expand to channels

}