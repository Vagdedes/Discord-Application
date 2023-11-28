<?php

use Discord\Parts\Guild\Guild;

class DiscordInviteTracker
{
    private DiscordPlan $plan;
    private static bool $isInitialized = false;

    //todo command to get invite stats about user
    //todo table to implement listeners for invites

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
                $userID = $invite->inviter?->id ?? null;

                if ($totalUses !== null && $code !== null
                    && $serverID !== null && $userID !== null) {
                    $query = get_sql_query(
                        BotDatabaseTable::BOT_INVITE_TRACKER,
                        array("id", "uses"),
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
                                "user_id" => $userID,
                                "invite_code" => $code,
                                "uses" => $totalUses,
                                "creation_date" => $invite->created_at,
                                "expiration_date" => $invite->expires_at
                            )
                        );
                    } else {
                        $query = $query[0];
                        $difference = $totalUses - $query->uses;

                        if ($difference > 0) {
                            set_sql_query(
                                BotDatabaseTable::BOT_INVITE_TRACKER,
                                array(
                                    "uses" => $totalUses,
                                ),
                                array(
                                    array("id", $query->id)
                                ),
                                null,
                                1
                            );
                        }
                    }
                }
            }
        });
    }
}