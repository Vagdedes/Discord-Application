<?php

class DiscordTicket
{
    private DiscordPlan $plan;

    public function __construct(DiscordPlan $plan)
    {
        $this->plan = $plan;
    }

    public function openTicket(string $key, int|string $userID)
    {

    }

    public function storeTicket()
    {

    }

    public function getTickets(int|string $userID, int|string|null $pastLookup = null): array
    {
        $cacheKey = array(__METHOD__, $this->plan->planID, $userID, $pastLookup);
        $cache = get_key_value_pair($cacheKey);

        if ($cache !== null) {
            return $cache;
        } else {
            $query = get_sql_query(
                BotDatabaseTable::BOT_TICKET_CREATIONS,
                null,
                array(
                    array("user_id", $userID),
                    array("deletion_date", null),
                    $pastLookup === null ? "" : array("creation_date", ">", get_past_date($pastLookup)),
                ),
                array(
                    "DESC",
                    "id"
                )
            );

            if (!empty($query)) {
                foreach ($query as $row) {
                    $row->key_value_pairs = get_sql_query(
                        BotDatabaseTable::BOT_TICKET_SUB_CREATIONS,
                        null,
                        array(
                            array("ticket_creation_id", $row->ticket_id),
                        )
                    );
                }
            }
            set_key_value_pair($cacheKey, $query, "15 seconds");
            return $query;
        }
    }
}