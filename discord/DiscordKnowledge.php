<?php

class DiscordKnowledge
{

    private DiscordPlan $plan;

    public function __construct(DiscordPlan $plan)
    {
        $this->plan = $plan;
    }

    public function getStatic($userID, ?int $limit = 0): array
    {
        set_sql_cache("1 second");
        return get_sql_query(
            DatabaseVariables::BOT_STATIC_KNOWLEDGE_TABLE,
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

    public function getDynamic($userID, ?int $limit = 0): array
    {
        set_sql_cache("1 second");
        return get_sql_query(
            DatabaseVariables::BOT_DYNAMIC_KNOWLEDGE_TABLE,
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
        rsort($final);
        return $final;
    }
}