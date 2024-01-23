<?php

use Discord\Parts\User\Member;

class DiscordPermissions
{
    private DiscordPlan $plan;
    private array $rolePermissions, $userPermissions;
    private const REFRESH_TIME = "3 seconds";

    public function __construct(DiscordPlan $plan)
    {
        $this->plan = $plan;
        $this->rolePermissions = array();
        $this->userPermissions = array();
        $query = get_sql_query(
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

        if (!empty($query)) {
            foreach ($query as $row) {
                $hash = $this->plan->utilities->hash($row->server_id, $row->role_id);

                if (array_key_exists($hash, $this->rolePermissions)) {
                    $this->rolePermissions[$hash][] = $row->permission;
                } else {
                    $this->rolePermissions[$hash] = array($row->permission);
                }
            }
        }
        $query = get_sql_query(
            BotDatabaseTable::BOT_USER_PERMISSIONS,
            array("server_id", "user_id", "permission"),
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
            foreach ($query as $row) {
                $hash = $this->plan->utilities->hash($row->server_id, $row->user_id);

                if (array_key_exists($hash, $this->userPermissions)) {
                    $this->userPermissions[$hash][] = $row->permission;
                } else {
                    $this->userPermissions[$hash] = array($row->permission);
                }
            }
        }
    }

    public function getRolePermissions(int|string|null $serverID, int|string $roleID): array
    {
        return $this->rolePermissions[$this->plan->utilities->hash($serverID, $roleID)] ?? array();
    }

    public function getUserPermissions(int|string|null $serverID, int|string|null $userID): array
    {
        return $this->userPermissions[$this->plan->utilities->hash($serverID, $userID)] ?? array();
    }

    public function roleHasPermission(int|string $serverID, int|string $roleID,
                                      string     $permission): bool
    {
        $hash = $this->plan->utilities->hash($serverID, $roleID);
        return array_key_exists($hash, $this->rolePermissions)
            && in_array($permission, $this->rolePermissions[$hash]);
    }

    public function userHasPermission(int|string|null $serverID, int|string|null $userID,
                                      string          $permission, bool $recursive = true): bool
    {
        $hash = $this->plan->utilities->hash($serverID, $userID);
        return array_key_exists($hash, $this->userPermissions)
            && in_array($permission, $this->userPermissions[$hash])
            || $recursive && ($this->userHasPermission($serverID, null, $permission, false)
                || $this->userHasPermission(null, $userID, $permission, false)
                || $this->userHasPermission(null, null, $permission, false));
    }

    public function hasPermission(Member $member, string $permission): bool
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

            if ($this->userHasPermission($member->guild_id, $member->id, $permission)) {
                $result = true;
            } else if (!empty($member->roles->first())) {
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

    public function addDiscordRole(Member $member, int|string $roleID): bool
    {
        foreach ($member->guild->roles as $serverRole) {
            if ($serverRole->id == $roleID) {
                if (!empty($member->roles->first())) {
                    foreach ($member->roles as $memberRole) {
                        if ($memberRole->id == $roleID) {
                            return false;
                        }
                    }
                }
                $member->addRole($serverRole);
                return true;
            }
        }
        return false;
    }

    public function removeDiscordRole(Member $member, int|string $roleID): bool
    {
        if (!empty($member->guild->roles->first())) {
            foreach ($member->guild->roles as $serverRole) {
                if ($serverRole->id == $roleID) {
                    $member->removeRole($serverRole);
                    return true;
                }
            }
        }
        return false;
    }

    public function hasRole(Member $member, int|string|array $roleID): bool
    {
        if (is_array($roleID)) {
            foreach ($roleID as $id) {
                if ($this->hasRole($member, $id)) {
                    return true;
                }
            }
            return false;
        } else if (!empty($member->roles->first())) {
            foreach ($member->roles as $serverRole) {
                if ($serverRole->id == $roleID) {
                    return true;
                }
            }
        }
        return false;
    }

}