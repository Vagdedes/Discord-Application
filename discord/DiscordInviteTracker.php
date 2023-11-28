<?php

use Discord\Parts\Guild\Guild;

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
                $this->track($guild);
            }
        }
    }

    private function track(Guild $guild): void
    {
        $guild->getInvites()->done(function (mixed $invites) {
            foreach ($invites as $invite) {
                $totalUses = $invite->uses ?? null;
                $code = $invite->code ?? null;
                $serverID = $invite->guild_id ?? null;

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
                                "server_id" => $serverID,
                                "user_id" => $invite->inviter?->id,
                                "invite_code" => $code,
                                "individual_uses" => $totalUses,
                                "total_uses" => $totalUses,
                                "creation_date" => get_current_date()
                            )
                        );
                    } else {
                        $difference = $totalUses - $query[0]->total_uses;

                        if ($difference > 0) {
                            sql_insert(
                                BotDatabaseTable::BOT_INVITE_TRACKER,
                                array(
                                    "server_id" => $serverID,
                                    "user_id" => $invite->inviter?->id,
                                    "invite_code" => $code,
                                    "individual_uses" => $difference,
                                    "total_uses" => $totalUses,
                                    "creation_date" => get_current_date()
                                )
                            );
                        }
                    }
                }
            }
        });
    }
}