<?php

class DiscordInviteTracker
{
    private DiscordPlan $plan;
    private static bool $isInitialized = false;

    public function __construct(DiscordPlan $plan)
    {
        $this->plan = $plan;

        if (!self::$isInitialized) {
            self::$isInitialized = true;

            foreach ($this->plan->discord->guilds as $guild) {
                if (!empty($guild->invites->toArray())) {
                    foreach ($guild->invites as $invite) {
                        $totalUses = $invite->uses ?? null;
                        $code = $invite->code;
                        $serverID = $invite->guild_id;

                        if ($totalUses !== null && $code !== null && $serverID !== null) {
                            $query = get_sql_query(
                                BotDatabaseTable::BOT_INVITE_TRACKER,
                                array("id", "individual_uses", "total_uses"),
                                array(
                                    array("server_id", $serverID),
                                    array("invite_code", $code),
                                    array("deletion_date", null)
                                ),
                                array(
                                    "DESC",
                                    "id"
                                ),
                                1
                            );

                            if (empty($query)) {
                                sql_insert(
                                    BotDatabaseTable::BOT_INVITE_TRACKER,
                                    array(
                                        array("server_id", $serverID),
                                        array("user_id", $invite->inviter?->id),
                                        array("invite_code", $code),
                                        array("individual_uses", $totalUses),
                                        array("total_uses", $totalUses),
                                        array("creation_date", get_current_date()),
                                    )
                                );
                            } else {
                                $difference = $totalUses - $query[0]->total_uses;

                                if ($difference > 0) {
                                    sql_insert(
                                        BotDatabaseTable::BOT_INVITE_TRACKER,
                                        array(
                                            array("server_id", $invite->guild_id),
                                            array("user_id", $invite->inviter?->id),
                                            array("invite_code", $invite->code),
                                            array("individual_uses", $difference),
                                            array("total_uses", $totalUses),
                                            array("creation_date", get_current_date()),
                                        )
                                    );
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}