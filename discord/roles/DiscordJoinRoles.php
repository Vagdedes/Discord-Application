<?php

use Discord\Parts\User\Member;

class DiscordJoinRoles
{
    private DiscordBot $bot;
    private array $roles;

    public function __construct(DiscordBot $bot)
    {
        $this->bot = $bot;
        $this->roles = get_sql_query(
            BotDatabaseTable::BOT_JOIN_ROLES,
            null,
            array_merge(array(
                array("deletion_date", null),
                null,
                array("expiration_date", "IS", null, 0),
                array("expiration_date", ">", get_current_date()),
                null
            ), $this->bot->utilities->getServersQuery())
        );
    }

    public function run(Member $member): void
    {
        if (!empty($this->roles)) {
            foreach ($this->roles as $role) {
                if ($role->server_id == $member->guild_id) {
                    $member->addRole($role->role_id)->done($this->bot->utilities->functionWithException(
                        function () use ($role, $member) {
                            sql_insert(
                                BotDatabaseTable::BOT_JOIN_ROLE_TRACKING,
                                array(
                                    "role_id" => $role->role_id,
                                    "user_id" => $member->id,
                                    "server_id" => $member->guild_id,
                                    "creation_date" => get_current_date()
                                )
                            );
                        }
                    ));
                }
            }
        }
    }
}