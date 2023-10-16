<?php

class DiscordModeration
{
    private DiscordPlan $plan;
    public array $punishments, $punishmentTypes;

    public function __construct(DiscordPlan $plan)
    {
        $this->plan = $plan;
        $this->punishmentTypes = get_sql_query(
            BotDatabaseTable::BOT_PUNISHMENT_TYPES,
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

        if (!empty($this->punishmentTypes)) {
            $query = get_sql_query(
                BotDatabaseTable::BOT_PUNISHMENTS,
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

            if (!empty($query)) {
                $punishments = array();

                foreach ($query as $punishment) {
                    foreach ($this->punishmentTypes as $punishmentType) {
                        if ($punishment->type == $punishmentType->id) {
                            $punishment->type = $punishmentType;
                            $punishments[] = $punishment;
                            break;
                        }
                    }
                }
                $this->punishments = $punishments;
            } else {
                $this->punishments = array();
            }
        } else {
            $this->punishments = array();
        }
        clear_memory(array(self::class), true);
    }

    public function getPunishments($userID): array
    {
        $cacheKey = array(__METHOD__, $userID);
        $cache = get_key_value_pair($cacheKey);

        if ($cache !== null) {
            return $cache;
        } else {
            $array = array();

            if (!empty($this->punishments)) {
                foreach ($this->punishments as $punishment) {
                    if ($punishment->user_id == $userID) {
                        $array[] = $punishment;
                    }
                }
            }
            set_key_value_pair($cacheKey, $array);
            return $array;
        }
    }

    public function hasPunishment(?int $type, $userID): ?object
    {
        $cacheKey = array(__METHOD__, $this->plan->planID, $type, $userID);
        $cache = get_key_value_pair($cacheKey);

        if ($cache !== null) {
            return $cache === true ? null : $cache;
        } else {
            $object = true;

            if (!empty($this->punishments)) {
                foreach ($this->punishments as $punishment) {
                    if ($punishment->user_id == $userID
                        && ($type === null || $punishment->type == $type)) {
                        $object = $punishment;
                        break;
                    }
                }
            }
            set_key_value_pair($cacheKey, $object);
            return $object === true ? null : $object;
        }
    }

    public function addPunishment(?int   $type, $botID, $executorID, $userID,
                                  string $reason, $duration = null): bool
    {
        if (empty($this->punishmentTypes)) {
            return false;
        } else {
            $found = false;

            foreach ($this->punishmentTypes as $punishmentType) {
                if ($punishmentType->id == $type) {
                    $found = true;
                    break;
                }
            }

            if ($found) {
                global $scheduler;
                $scheduler->addTask(
                    null,
                    "sql_insert",
                    array(
                        BotDatabaseTable::BOT_PUNISHMENTS,
                        array(
                            "type" => $type,
                            "plan_id" => $this->plan->planID,
                            "bot_id" => $botID,
                            "executor_id" => $executorID,
                            "user_id" => $userID,
                            "creation_date" => get_current_date(),
                            "creation_reason" => $reason,
                            "expiration_date" => $duration === null ? null : get_future_date($duration)
                        )
                    )
                );
                return true;
            } else {
                return false;
            }
        }
    }

}