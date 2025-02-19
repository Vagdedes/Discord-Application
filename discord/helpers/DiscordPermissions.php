<?php

use Discord\Parts\Guild\Guild;
use Discord\Parts\User\Member;

class DiscordPermissions
{
    private DiscordBot $bot;

    public function __construct(DiscordBot $bot)
    {
        $this->bot = $bot;
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