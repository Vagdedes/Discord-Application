<?php

class DiscordKnowledge
{

    private DiscordPlan $plan;

    public function __construct(DiscordPlan $plan)
    {
        $this->plan = $plan;
    }

    public function addDynamicKnowledge($botID, $userID, $message, $expirationDate = null): void
    {
        global $scheduler;
        $scheduler->addTask(
            null,
            "sql_insert",
            array(
                BotDatabaseTable::BOT_DYNAMIC_KNOWLEDGE,
                array(
                    "plan_id" => $this->plan->planID,
                    "bot_id" => $botID,
                    "user_id" => $userID,
                    "information" => $message,
                    "creation_date" => get_current_date(),
                    "expiration_date" => $expirationDate
                )
            )
        );
    }

    public function getStatic($userID, ?int $limit = 0, $object = true): array
    {
        set_sql_cache(DiscordProperties::SYSTEM_REFRESH_MILLISECONDS / 1000);
        $array = get_sql_query(
            BotDatabaseTable::BOT_STATIC_KNOWLEDGE,
            null,
            array(
                array("user_id", $userID),
                array("deletion_date", null),
                null,
                array("plan_id", "IS", null, 0),
                array("plan_id", $this->plan->planID),
                null,
                null,
                array("expiration_date", "IS", null, 0),
                array("expiration_date", ">", get_current_date()),
                null
            ),
            array(
                "DESC",
                "id"
            ),
            $limit
        );

        if (!empty($array)) {
            $time = time();

            foreach ($array as $arrayKey => $row) {
                if ($row->information_url === null) {
                    unset($row->information_url);
                    unset($row->information_expiration);
                    $row->information = $row->information_value;
                    unset($row->information_value);
                } else if ($row->information_expiration !== null && $row->information_expiration > $time) {
                    unset($row->information_url);
                    unset($row->information_expiration);
                    $row->information = $row->information_value;
                    unset($row->information_value);
                } else {
                    $doc = starts_with($row->information_url, "https://docs.google.com/")
                        ? get_raw_google_doc($row->information_url, false, 5) :
                        timed_file_get_contents($row->information_url, 5);

                    if ($doc !== null) {
                        $row->information = $doc;
                        unset($row->information_url);
                        unset($row->information_expiration);
                        unset($row->information_value);
                        set_sql_query(
                            BotDatabaseTable::BOT_STATIC_KNOWLEDGE,
                            array(
                                "information_value" => $doc,
                                "information_expiration" => get_future_date($row->information_duration)
                            ),
                            array(
                                array("id", $row->id)
                            )
                        );
                    } else {
                        unset($array[$arrayKey]);
                    }
                }
            }
        }

        if (!$object) {
            foreach ($array as $arrayKey => $row) {
                $array[$arrayKey] = $row->information_value;
            }
        }
        return $array;
    }

    public function getDynamic($userID, ?int $limit = 0, $object = true): array
    {
        set_sql_cache("1 second");
        $array = get_sql_query(
            BotDatabaseTable::BOT_DYNAMIC_KNOWLEDGE,
            null,
            array(
                array("user_id", $userID),
                array("deletion_date", null),
                null,
                array("plan_id", "IS", null, 0),
                array("plan_id", $this->plan->planID),
                null,
                null,
                array("expiration_date", "IS", null, 0),
                array("expiration_date", ">", get_current_date()),
                null
            ),
            array(
                "DESC",
                "id"
            ),
            $limit
        );

        if (!$object) {
            foreach ($array as $arrayKey => $row) {
                $array[$arrayKey] = $row->information_value;
            }
        }
        return $array;
    }

    public function getAll($userID, ?int $limit = 0, $object = true): array
    {
        $final = array();
        $static = $this->getStatic($userID, $limit, $object);
        $dynamic = $this->getDynamic($userID, $limit, $object);

        if (!empty($static)) {
            foreach ($static as $row) {
                $row->static = true;
                $final[] = $row;
            }
        }
        if (!empty($dynamic)) {
            foreach ($dynamic as $row) {
                $row->static = false;
                $final[] = $row;
            }
        }
        return $final;
    }
}