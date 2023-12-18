<?php

use Discord\Builders\MessageBuilder;
use Discord\Parts\User\Member;

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

    public function create(Member           $member,
                           int|float|string $name,
                           int|float|string $title, int|float|string $description,
                           bool             $interactionBased, bool $commandBased,
                           bool             $allowDeletion,
                           bool             $maxChoices, bool $allowSameChoice): ?string
    {
        $get = $this->get($name);

        if ($get !== null) {
            return "This user poll already exists.";
        }
        return null;
    }

    public function delete(Member           $member,
                           int|float|string $name): ?string
    {
        $get = $this->get($name);

        if ($get === null) {
            return "This user poll does not exist.";
        }
        return null;
    }

    // Separator

    public function start(Member           $member,
                          int|float|string $name, string $duration): ?string
    {
        $get = $this->get($name);

        if ($get === null) {
            return "This user poll does not exist.";
        } else if (!$this->owns($member, $get)) {
            return "You do not own this user poll.";
        } else if ($this->isRunning($get)) {
            return "This user poll is already running.";
        }
        return null;
    }

    public function end(Member           $member,
                        int|float|string $name): ?string
    {
        $get = $this->get($name);

        if ($get === null) {
            return "This user poll does not exist.";
        } else if (!$this->owns($member, $get)) {
            return "You do not own this user poll.";
        } else if ($this->isRunning($get)) {
            return "This user poll is already running.";
        }
        return null;
    }

    private function finish(object $query): void
    {

    }

    // Separator

    private function getPicks(object $query): array
    {
        return array();
    }

    private function hasPicked(object $query): bool
    {
        return !empty($this->getPicks($query));
    }

    public function setPick(int|float|string $name, int|float|string $choice, bool $set = true): ?string
    {
        return null;
    }

    // Separator

    private function hasPermission(object $query): bool
    {
        return false;
    }

    public function setRequiredPermission(int|float|string $name, int|string $permission, bool $set = true): ?string
    {
        return null;
    }

    // Separator

    private function hasRequiredRole(object $query): bool
    {
        return false;
    }

    public function setRequiredRole(int|float|string $name, int|string $roleID, bool $set = true): ?string
    {
        return null;
    }

    // Separator

    public function getResults(int|float|string $name): MessageBuilder
    {
        return MessageBuilder::new();
    }

    // Separator

    private function get(int|float|string $name): ?object
    {
        return null;
    }

    private function update(object $query): void
    {

    }

    // Separator

    private function isRunning(object $query): bool
    {
        set_sql_cache("1 second");
        return !empty(get_sql_query(
            BotDatabaseTable::BOT_POLL_TRACKING,
            array("id"),
            array(
                array("deletion_date", null),
                array("running", null),
                array("id", $query->id)
            ),
            null,
            1
        ));
    }

    // Separator

    private function checkExpired(): void
    {
        $query = get_sql_query(
            BotDatabaseTable::BOT_POLL_TRACKING,
            null,
            array(
                array("plan_id", $this->plan->planID),
                array("deletion_date", null),
                array("running", null),
                array("expiration_date", "<", get_current_date())
            )
        );

        if (!empty($query)) {
            foreach ($query as $poll) {
                $this->finish($poll);
            }
        }
    }

    private function owns(Member $member, object $query): bool
    {
        set_sql_cache("1 second");
        return !empty(get_sql_query(
                BotDatabaseTable::BOT_POLLS,
                array("id"),
                array(
                    array("id", $query->id),
                    array("user_id", $member->id)
                ),
                null,
                1
            )) || $this->plan->permissions->hasPermission($member, "idealistic.user.polls.manage");
    }
}