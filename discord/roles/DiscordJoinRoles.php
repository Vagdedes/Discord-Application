<?php

use Discord\Parts\User\Member;

class DiscordJoinRoles
{
    private DiscordPlan $plan;
    private array $roles;

    public function __construct(DiscordPlan $plan)
    {
        $this->plan = $plan;
        $this->roles = get_sql_query(
            BotDatabaseTable::BOT_JOIN_ROLES,
            null,
            array(
                array("plan_id", $this->plan->planID),
                array("deletion_date", null),
                null,
                array("expiration_date", "IS", null, 0),
                array("expiration_date", ">", get_current_date()),
                null
            )
        );
    }

    public function run(Member $member): void
    {
        if (!empty($this->roles)) {
            foreach ($this->roles as $role) {
                if ($role->server_id == $member->guild_id) {
                    $member->addRole($role->role_id)->done(function () use ($role, $member) {
                        sql_insert(
                            BotDatabaseTable::BOT_JOIN_ROLE_TRACKING,
                            array(
                                "plan_id" => $this->plan->planID,
                                "role_id" => $role->role_id,
                                "user_id" => $member->id,
                                "server_id" => $member->guild_id,
                                "creation_date" => get_current_date()
                            )
                        );
                    });
                }
            }
        }
    }
}