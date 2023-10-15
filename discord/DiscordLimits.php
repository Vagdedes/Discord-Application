<?php

class DiscordLimits
{
    private DiscordPlan $plan;
    private array $limits, $storage;

    public function __construct(DiscordPlan $plan)
    {
        $this->plan = $plan;
        $this->storage = array();
        $this->limits = get_sql_query(
            BotDatabaseTable::BOT_MESSAGE_LIMITS,
            null,
            array(
                array("deletion_date", null),
                null,
                array("plan_id", "IS", null, 0),
                array("plan_id", $this->plan->planID),
                null,
                null,
                array("expiration_date", "IS", null, 0),
                array("expiration_date", ">", get_current_date()),
                null
            )
        );
        clear_memory(array(self::class), true);
    }

    public function store(): void
    {
        if (!empty($this->storage)) {
            foreach ($this->storage as $object) {
                $object->limit_type = $object->limit_type->id;
                set_sql_query(
                    BotDatabaseTable::BOT_MESSAGE_LIMIT_TRACKING,
                    json_decode(json_encode($object), true),
                    array(
                        array("id", $object->id)
                    ),
                    null,
                    1
                );
            }
        }
    }

    public function isLimited($serverID, $channelID, $userID, $botID): array
    {
        $cacheKey = array(__METHOD__, $this->plan->planID, $serverID, $channelID, $userID);
        $cache = get_key_value_pair($cacheKey);

        if ($cache === null) {
            $cache = array();

            if (!empty($this->limits)) {
                foreach ($this->limits as $limit) {
                    if (($limit->channel_id === null || $limit->channel_id === $channelID)
                        && ($limit->server_id === null || $limit->server_id === $serverID)
                        && ($limit->user_id === null || $limit->user_id === $userID)) {
                        $repeat = 0;

                        while ($repeat < 2) { // Here for new rows to be inserted and query to be re-run
                            $query = get_sql_query(
                                BotDatabaseTable::BOT_MESSAGE_LIMIT_TRACKING,
                                null,
                                array(
                                    array("limit_type", $limit->id),
                                    array("user_id", $userID),
                                ),
                                null,
                                1
                            );

                            if (empty($query)) {
                                $repeat++;
                                sql_insert(
                                    BotDatabaseTable::BOT_MESSAGE_LIMIT_TRACKING,
                                    array(
                                        "limit_type" => $limit->id,
                                        "bot_id" => $botID,
                                        "user_id" => $userID,
                                        "counter" => 0,
                                        "creation_date" => get_current_date(),
                                        "expiration_date" => get_future_date($limit->duration)
                                    )
                                );
                            } else {
                                $query[0]->limit_type = $limit;
                                $cache[] = $repeat === 0 ? $this->recreate($query[0])[1] : $query[0];
                                break;
                            }
                        }
                    }
                }
            }
            set_key_value_pair($cacheKey, $cache);
        }

        if (!empty($cache)) {
            $remaining = array();

            foreach ($cache as $arrayKey => $object) {
                $object = $this->recreate($object, 1);

                if ($object[0]) {
                    $cache[$arrayKey] = $object[1];
                    unset($this->storage[$object[1]->id]);
                } else {
                    $object = $object[1];

                    if ($object->counter === $object->limit_type->limit) {
                        $remaining[] = $object;
                    } else if (empty($remaining)) { // Do not run more limits if at least one is found to be reached
                        $object->counter++;
                    }
                    $this->storage[$object->id] = $object;
                }
            }
            return $remaining;
        } else {
            return $cache;
        }
    }

    private function recreate(object $object, int $defaultCounter = 0): array
    {
        $date = get_current_date();

        if ($object->expiration_date <= $date) {
            $futureDate = get_future_date($object->limit_type->duration);
            $object->counter = 1;
            $object->creation_date = $date;
            $object->expiration_date = $futureDate;
            set_sql_query(
                BotDatabaseTable::BOT_MESSAGE_LIMIT_TRACKING,
                array(
                    "counter" => $defaultCounter,
                    "creation_date" => $date,
                    "expiration_date" => $futureDate,
                ),
                array(
                    array("id", $object->id)
                ),
                null,
                1
            );
            return array(true, $object);
        } else {
            return array(false, $object);
        }
    }
}