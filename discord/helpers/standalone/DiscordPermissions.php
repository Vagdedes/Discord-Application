<?php

use Discord\Parts\Guild\Guild;
use Discord\Parts\User\Member;

class DiscordPermissions
{
    private DiscordBot $bot;
    private array $rolePermissions, $userPermissions;
    private const REFRESH_TIME = "3 seconds";

    public function __construct(DiscordBot $bot)
    {
        $this->bot = $bot;
        $this->rolePermissions = array();
        $this->userPermissions = array();
        $query = get_sql_query(
            BotDatabaseTable::BOT_ROLE_PERMISSIONS,
            array("server_id", "role_id", "permission"),
            array_merge(array(
                array("deletion_date", null),
                null,
                array("expiration_date", "IS", null, 0),
                array("expiration_date", ">", get_current_date()),
                null
            ), $this->bot->utilities->getServersQuery())
        );

        if (!empty($query)) {
            foreach ($query as $row) {
                $hash = $this->bot->utilities->hash($row->server_id, $row->role_id);

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
            array_merge(array(
                array("deletion_date", null),
                null,
                array("expiration_date", "IS", null, 0),
                array("expiration_date", ">", get_current_date()),
                null
            ), $this->bot->utilities->getServersQuery())
        );

        if (!empty($query)) {
            foreach ($query as $row) {
                $hash = $this->bot->utilities->hash($row->server_id, $row->user_id);

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
        return $this->rolePermissions[$this->bot->utilities->hash($serverID, $roleID)] ?? array();
    }

    public function getUserPermissions(int|string|null $serverID, int|string|null $userID): array
    {
        return $this->userPermissions[$this->bot->utilities->hash($serverID, $userID)] ?? array();
    }

    private function roleHasPermission(int|string $serverID, int|string $roleID,
                                       string     $permission): bool
    {
        $hash = $this->bot->utilities->hash($serverID, $roleID);
        return array_key_exists($hash, $this->rolePermissions)
            && in_array($permission, $this->rolePermissions[$hash]);
    }

    private function userHasPermission(int|string|null $serverID, int|string|null $userID,
                                       string          $permission, bool $recursive = true): bool
    {
        $hash = $this->bot->utilities->hash($serverID, $userID);
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
            $member->id,
            $permission
        );
        $cache = get_key_value_pair($cacheKey);

        if ($cache !== null) {
            return $cache;
        } else {
            $result = false;

            if ($this->userHasPermission($member->guild_id, $member->id, "*")
                || $this->userHasPermission($member->guild_id, $member->id, $permission)) {
                $result = true;
            } else if (!empty($member->roles->first())) {
                foreach ($member->roles as $role) {
                    if ($this->roleHasPermission($role->guild_id, $role->id, "*")
                        || $this->roleHasPermission($role->guild_id, $role->id, $permission)) {
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

    public function isStaff(Member|int|string $member, Guild $guild = null): bool
    {
        if (!($member instanceof Member)) {
            $member = $guild->members->toArray()[$member] ?? null;

            if ($member === null) {
                return false;
            }
        }
        if (!empty($member->roles->first())) {
            foreach ($member->roles as $role) {
                if ($role->permissions->kick_members
                    || $role->permissions->ban_members
                    || $role->permissions->mute_members
                    || $role->permissions->deafen_members
                    || $role->permissions->move_members
                    || $role->permissions->administrator
                    || $role->permissions->manage_guild
                    || $role->permissions->manage_nicknames
                    || $role->permissions->manage_roles
                    || $role->permissions->manage_threads
                    || $role->permissions->manage_channels
                    || $role->permissions->manage_webhooks
                    || $role->permissions->moderate_members) {
                    return true;
                }
            }
        }
        return false;
    }
}