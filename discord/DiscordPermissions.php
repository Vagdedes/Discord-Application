<?php

use Discord\Parts\User\Member;

class DiscordPermissions
{
    private DiscordPlan $plan;
    private array $permissions;
    private const REFRESH_TIME = "3 seconds";

    public function __construct(DiscordPlan $plan)
    {
        $this->plan = $plan;
        $this->permissions = get_sql_query(
            BotDatabaseTable::BOT_ROLE_PERMISSIONS,
            array("server_id", "role_id", "permission"),
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
                $hash = $this->hash($row->server_id, $row->role_id);

                if (array_key_exists($hash, $this->permissions)) {
                    $this->permissions[$hash][] = $row->permission;
                } else {
                    $this->permissions[$hash] = array($row->permission);
                }
            }
        }
    }

    public function getRolePermissions(int|string $serverID, int|string $roleID): array
    {
        return $this->permissions[$this->hash($serverID, $roleID)] ?? array();
    }

    public function roleHasPermission(int|string $serverID, int|string $roleID,
                                      string     $permission): bool
    {
        $hash = $this->hash($serverID, $roleID);
        return array_key_exists($hash, $this->permissions)
            && in_array($permission, $this->permissions[$hash]);
    }

    public function userHasPermission(Member $member, string $permission): bool
    {
        $cacheKey = array(
            __METHOD__,
            $this->plan->planID,
            $member->id,
            $permission
        );
        $cache = get_key_value_pair($cacheKey);

        if ($cache !== null) {
            return $cache;
        } else {
            $result = false;

            if (!empty($member->roles->getIterator())) {
                foreach ($member->roles as $role) {
                    if ($this->roleHasPermission($role->guild_id, $role->id, $permission)) {
                        $result = true;
                        break;
                    }
                }
            }
            set_key_value_pair($cacheKey, $result, self::REFRESH_TIME);
            return $result;
        }
    }

    private function hash(int|string $serverID, int|string $roleID): int
    {
        return string_to_integer($serverID . $roleID);
    }
}