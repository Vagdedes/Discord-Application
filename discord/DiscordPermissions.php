<?php

class DiscordPermissions
{
    private DiscordPlan $plan;

    public function __construct(DiscordPlan $plan)
    {
        $this->plan = $plan;
    }

    public function getRolePermissions(int|string $roleID): bool
    {
        return true;
    }

    public function roleHasPermission(int|string $roleID, string $permission): bool
    {
        return true;
    }

    public function userHasPermission(int|string $userID, string $permission): bool
    {
        return true;
    }
}