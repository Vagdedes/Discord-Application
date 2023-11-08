<?php
class DiscordPermissions
{
    private DiscordPlan $plan;

    public function __construct(DiscordPlan $plan)
    {
        $this->plan = $plan;
    }

    public function getRolePermissions(int|string $roleID) {

    }

    public function roleHasPermission(int|string $roleID) {

    }

    public function userHasPermission(int|string $userID) {

    }
}