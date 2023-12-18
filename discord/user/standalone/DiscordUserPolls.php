<?php

use Discord\Builders\MessageBuilder;

class DiscordUserPolls
{
    private DiscordPlan $plan;
    private array $polls;

    private const REFRESH_TIME = "15 seconds";

    public function __construct(DiscordPlan $plan)
    {
        $this->plan = $plan;
        $this->checkExpired();
    }

    //todo max 25 choices, commands

    public function create(): ?string
    {
        return null;
    }

    public function delete(): ?string
    {
        return null;
    }

    // Separator

    public function start(): ?string
    {
        return null;
    }

    public function end(): ?string
    {
        return null;
    }

    // Separator

    public function pick(): ?string
    {
        return null;
    }

    public function unpick(): ?string
    {
        return null;
    }

    // Separator

    public function setRequiredPermission(): ?string
    {
        return null;
    }

    public function setRequiredRole(): ?string
    {
        return null;
    }

    // Separator

    public function getResults(): MessageBuilder
    {
        return MessageBuilder::new();
    }

    // Separator

    private function update(): void
    {

    }

    private function checkExpired(): void
    {

    }

    private function isRunning(): bool
    {
        return false;
    }
}