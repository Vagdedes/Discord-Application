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
        sql_insert(
            BotDatabaseTable::BOT_DYNAMIC_KNOWLEDGE,
            array(
                "plan_id" => $this->plan->planID,
                "bot_id" => $botID,
                "user_id" => $userID,
                "information" => $message,
                "creation_date" => get_current_date(),
                "expiration_date" => $expirationDate
            )
        );
    }

    public function getStatic($userID, ?int $limit = 0): array
    {
        set_sql_cache("1 second");
        $array = get_sql_query(
            BotDatabaseTable::BOT_STATIC_KNOWLEDGE,
            null,
            array(
                array("plan_id", $this->plan->planID),
                array("user_id", $userID),
                array("deletion_date", null),
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
        //todo query for urls and make the keys same to dynamic knowledge
        return $array;
    }

    public function getDynamic($userID, ?int $limit = 0): array
    {
        set_sql_cache("1 second");
        return get_sql_query(
            BotDatabaseTable::BOT_DYNAMIC_KNOWLEDGE,
            null,
            array(
                array("plan_id", $this->plan->planID),
                array("user_id", $userID),
                array("deletion_date", null),
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
    }

    public function getAll($userID, ?int $limit = 0): array
    {
        $final = array();
        $static = $this->getStatic($userID, $limit);
        $dynamic = $this->getDynamic($userID, $limit);

        if (!empty($static)) {
            foreach ($static as $row) {
                $final[strtotime($row->creation_date)] = $row;
            }
        }
        if (!empty($dynamic)) {
            foreach ($dynamic as $row) {
                $final[strtotime($row->creation_date)] = $row;
            }
        }
        krsort($final);
        return $final;
    }
}