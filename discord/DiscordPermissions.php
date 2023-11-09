<?php

use Discord\Parts\User\User;

class DiscordPermissions
{
    private DiscordPlan $plan;
    private array $permissions;

    public function __construct(DiscordPlan $plan)
    {
        $this->plan = $plan;
        $this->permissions = get_sql_query(
            BotDatabaseTable::BOT_ROLE_PERMISSIONS,
            array("role_id", "permission"),
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

        if (!empty($this->permissions)) {
            foreach ($this->permissions as $row) {
                $this->permissions[$row->role_id] = $row->permission;
            }
        }
    }

    public function getRolePermissions(int|string $roleID): array
    {
        return $this->permissions[$roleID] ?? array();
    }

    public function roleHasPermission(int|string $roleID, string $permission): bool
    {
        return array_key_exists($roleID, $this->permissions)
            && in_array($permission, $this->permissions[$roleID]);
    }

    public function userHasPermission(User $user, string $permission): bool
    {
        return true; // todo
    }
}