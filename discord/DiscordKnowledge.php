<?php

class DiscordKnowledge
{

    private DiscordPlan $plan;

    public function __construct(DiscordPlan $plan)
    {
        $this->plan = $plan;
    }

    public function get($userID, ?int $limit = 0, $object = true): array
    {
        set_sql_cache(DiscordProperties::SYSTEM_REFRESH_MILLISECONDS / 1_000);
        $array = get_sql_query(
            BotDatabaseTable::BOT_KNOWLEDGE,
            null,
            array(
                array("deletion_date", null),
                array("applicationID", $this->plan->applicationID),
                null,
                array("user_id", "IS", null, 0),
                array("user_id", $userID),
                null,
                null,
                array("plan_id", "IS", null, 0),
                array("plan_id", $this->plan->planID),
                $this->plan->family !== null ? array("family", $this->plan->family) : "",
                null,
                null,
                array("expiration_date", "IS", null, 0),
                array("expiration_date", ">", get_current_date()),
                null
            ),
            "plan_id ASC, priority DESC",
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
                            BotDatabaseTable::BOT_KNOWLEDGE,
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
}