<?php

use Discord\Builders\MessageBuilder;
use Discord\Parts\User\Member;

class DiscordUserPolls
{
    private DiscordPlan $plan;
    private array $polls;

    private const
        REFRESH_TIME = "15 seconds",
        MANAGE_PERMISSION = "idealistic.user.polls.manage",

        NOT_EXISTS = "This user poll does not exist.",
        NOT_OWNED = "You do not own this user poll.";

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
        } else if (!$this->owns($member, $get)) {
            return self::NOT_OWNED;
        } else {
            $result = $this->endRaw($get);

            if ($result !== null) {
                return $result;
            } else if (set_sql_query(
                BotDatabaseTable::BOT_POLLS,
                array(
                    "deletion_date" => get_current_date()
                ),
                array(
                    array("id", $get->id)
                ),
                null,
                1
            )) {
                return null;
            } else {
                return "Failed to delete this user poll from the database.";
            }
        }
    }

    // Separator

    public function start(Member           $member,
                          int|float|string $name, string $duration): ?string
    {
        $get = $this->get($name);

        if ($get === null) {
            return self::NOT_EXISTS;
        } else if (!$this->owns($member, $get)) {
            return self::NOT_OWNED;
        } else if ($this->isRunning($get)) {
            return "This user poll is already running.";
        }
        //todo
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
        return $this->endRaw($get);
    }

    public function endRaw(object $query): ?string
    {
        //todo
        return null;
    }

    private function finish(object $query): void
    {
        //todo
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
        if ($set) {

        } else {

        }
        return null;
    }

    // Separator

    private function getPermissions(object $query): array
    {
        set_sql_cache("1 second");
        return get_sql_query(
            BotDatabaseTable::BOT_POLL_PERMISSIONS,
            null,
            array(
                array("deletion_date", null),
                array("poll_id", $query->id)
            )
        );
    }

    private function hasPermission(Member $member, object $query): bool
    {
        $permissions = $this->getPermissions($query);

        if (!empty($permissions)) {
            foreach ($permissions as $permission) {
                if (!$this->plan->permissions->hasPermission($member, $permission->permission_id)) {
                    return false;
                }
            }
        }
        return true;
    }

    public function setRequiredPermission(int|float|string $name, int|string $permission, bool $set = true): ?string
    {
        $query = $this->get($name);

        if ($query === null) {
            return self::NOT_EXISTS;
        } else {
            $permissions = $this->getPermissions($query);
            //todo
            return null;
        }
    }

    // Separator

    private function getRequiredRoles(object $query): array
    {
        set_sql_cache("1 second");
        return get_sql_query(
            BotDatabaseTable::BOT_POLL_ROLES,
            null,
            array(
                array("deletion_date", null),
                array("poll_id", $query->id)
            )
        );
    }

    private function hasRequiredRole(Member $member, object $query): bool
    {
        $roles = $this->getRequiredRoles($query);

        if (!empty($roles)) {
            $memberRoles = $member->roles->toArray();

            if (empty($memberRoles)) {
                return false;
            }
            foreach ($roles as $role) {
                $has = false;

                foreach ($memberRoles as $memberRole) {
                    if ($role->role_id == $memberRole->id) {
                        $has = true;
                        break;
                    }
                }
                if (!$has) {
                    return false;
                }
            }
        }
        return false;
    }

    public function setRequiredRole(int|float|string $name, int|string $roleID, bool $set = true): ?string
    {
        $query = $this->get($name);

        if ($query === null) {
            return self::NOT_EXISTS;
        } else {
            $roles = $this->getRequiredRoles($query);
            //todo
            return null;
        }
    }

    // Separator

    public function getResults(int|float|string $name): MessageBuilder
    {
        return MessageBuilder::new();
    }

    // Separator

    private function get(int|float|string $name): ?object
    {
        $query = get_sql_query(
            BotDatabaseTable::BOT_POLLS,
            array("id"),
            array(
                array("deletion_date", null),
                array("name", $name),
            ),
            null,
            1
        );
        return empty($query) ? null : $query[0];
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
        return $query->user_id == $member->id
            || $this->plan->permissions->hasPermission($member, self::MANAGE_PERMISSION);
    }
}