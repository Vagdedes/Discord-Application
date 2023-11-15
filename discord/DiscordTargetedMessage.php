<?php

use Discord\Builders\MessageBuilder;
use Discord\Parts\Channel\Channel;
use Discord\Parts\Channel\Message;
use Discord\Parts\Embed\Embed;

class DiscordTargetedMessage
{
    private DiscordPlan $plan;
    private array $targets;

    private const REFRESH_TIME = "15 seconds";

    public function __construct(DiscordPlan $plan)
    {
        $this->plan = $plan;
        $this->targets = get_sql_query(
            BotDatabaseTable::BOT_TARGETED_MESSAGES,
            null,
            array(
                array("plan_id", $plan->planID),
                array("deletion_date", null),
                null,
                array("expiration_date", "IS", null, 0),
                array("expiration_date", ">", get_current_date()),
                null
            )
        );

        foreach ($this->targets as $target) {
            $this->initiate($target);
        }
    }

    private function initiate(object|string|int $query): void
    {
        if (!is_object($query)) {
            $query = null;

            if (!empty($this->targets)) {
                foreach ($this->targets as $target) {
                    if ($target->target_id == $query) {
                        $query = $target;
                        break;
                    }
                }
            }

            if ($query === null) {
                return;
            }
        }
        if ($query->max_open_general !== null
            && $this->hasMaxOpen($query->id, null, $query->max_open_general)) {
            return;
        }
        $members = null;

        foreach ($this->plan->discord->guilds as $guild) {
            if ($guild->id == $query->server_id) {
                $members = $guild->members;
                break;
            }
        }

        if (!empty($members)) {
            return;
        } else {
            $members = $members->toArray();
            ksort($members);
        }
        $date = get_current_date(); // Always first

        for ($i = 0; $i < max($this->checkExpired(), 1); $i++) {
            $member = $members[rand(0, sizeof($members) - 1)];

            if ($query->max_open_per_user !== null
                && $this->hasMaxOpen($query->id, $member->user->id, $query->max_open_per_user)
                && ($query->close_oldest_if_max_open === null || !$this->closeOldest($query))) {
                return;
            }
            $message = MessageBuilder::new()->setContent(
                $this->plan->instructions->replace(
                    array($query->inception_message),
                    $this->plan->instructions->getObject(
                        $member->guild_id,
                        $member->guild->name,
                        null,
                        null,
                        null,
                        null,
                        $member->user->id,
                        $member->username,
                        $member->displayname,
                        null,
                        null
                    )
                )[0]
            );

            while (true) {
                $targetID = random_number(19);

                if (empty(get_sql_query(
                    BotDatabaseTable::BOT_TARGETED_MESSAGE_CREATIONS,
                    array("target_id"),
                    array(
                        array("target_creation_id", $targetID)
                    ),
                    null,
                    1
                ))) {
                    $insert = array(
                        "target_id" => $query->id,
                        "target_creation_id" => $targetID,
                        "user_id" => $member->user->id,
                        "server_id" => $query->server_id,
                        "creation_date" => $date,
                    );

                    if ($query->create_channel_category_id !== null) {
                        $rolePermissions = get_sql_query(
                            BotDatabaseTable::BOT_TARGETED_MESSAGE_ROLES,
                            array("allow", "deny", "role_id"),
                            array(
                                array("deletion_date", null),
                                array("target_id", $query->id)
                            )
                        );

                        if (!empty($rolePermissions)) {
                            foreach ($rolePermissions as $arrayKey => $role) {
                                $rolePermissions[$arrayKey] = array(
                                    "id" => $role->role_id,
                                    "type" => "role",
                                    "allow" => empty($role->allow) ? $query->allow_permission : $role->allow,
                                    "deny" => empty($role->deny) ? $query->allow_permission : $role->deny
                                );
                            }
                        }
                        $memberPermissions = array(
                            array(
                                "id" => $member->user->id,
                                "type" => "member",
                                "allow" => $query->allow_permission,
                                "deny" => $query->allow_permission
                            )
                        );
                        $this->plan->utilities->createChannel(
                            $member->guild,
                            Channel::TYPE_TEXT,
                            $query->create_channel_category_id,
                            (empty($query->create_channel_name)
                                ? $this->plan->utilities->getUsername($member->user->id)
                                : $query->create_channel_name)
                            . "-" . $targetID,
                            $query->create_channel_topic,
                            $rolePermissions,
                            $memberPermissions
                        )->done(function (Channel $channel) use ($targetID, $insert, $member, $message) {
                            $insert["channel_id"] = $channel->id;

                            if (sql_insert(BotDatabaseTable::BOT_TARGETED_MESSAGE_CREATIONS, $insert)) {
                                $channel->sendMessage($message);
                            } else {
                                global $logger;
                                $logger->logError(
                                    $this->plan->planID,
                                    "Failed to insert target creation of user: " . $member->user->id
                                );
                            }
                        });
                    } else if ($query->create_channel_id !== null) {
                        $channel = $this->plan->discord->getChannel($query->create_channel_id);

                        if ($channel !== null
                            && $channel->guild_id == $query->server_id) {

                            if (true) { //todo create thread
                                $insert["channel_id"] = $channel->id;
                                $insert["created_thread_id"] = $channel->guild_id;

                                if (sql_insert(BotDatabaseTable::BOT_TARGETED_MESSAGE_CREATIONS, $insert)) {
                                    $channel->sendMessage($message);
                                } else {
                                    global $logger;
                                    $logger->logError(
                                        $this->plan->planID,
                                        "Failed to insert target creation of user: " . $member->user->id
                                    );
                                }
                            }
                        } else {
                            global $logger;
                            $logger->logError(
                                $this->plan->planID,
                                "Failed to find target channel with ID: " . $query->id
                            );
                        }
                    } else {
                        global $logger;
                        $logger->logError(
                            $this->plan->planID,
                            "(2) Invalid target with ID: " . $query->id
                        );
                    }
                    break;
                }
            }
        }
    }

    public function track(Message $message): void
    {
        if (!empty($message->content)) {
            $channel = $message->channel;
            set_sql_cache("1 second");
            $query = get_sql_query(
                BotDatabaseTable::BOT_TARGETED_MESSAGE_CREATIONS,
                array("id", "target_creation_id", "expiration_date", "created_thread_id"),
                array(
                    array("server_id", $channel->guild_id),
                    array("channel_id", $channel->id),
                ),
                null,
                1
            );

            if (!empty($query)) {
                $query = $query[0];

                if ($query->deletion_date !== null) {
                    if ($query->created_thread_id !== null) {
                        $this->plan->utilities->deleteThread(
                            $channel,
                            $query->created_thread_id
                        );
                    } else {
                        $channel->guild->channels->delete($channel);
                    }
                } else if ($query->expiration_date !== null
                    && get_current_date() > $query->expiration_date
                    || $query->expiration_date !== null
                    && get_current_date() > $query->expiration_date) {
                    if (set_sql_query(
                        BotDatabaseTable::BOT_TARGETED_MESSAGE_CREATIONS,
                        array(
                            "expired" => 1
                        ),
                        array(
                            array("id", $query->id)
                        ),
                        null,
                        1
                    )) {
                        if ($query->created_thread_id !== null) {
                            $this->plan->utilities->deleteThread(
                                $channel,
                                $query->created_thread_id
                            );
                        } else {
                            $channel->guild->channels->delete($channel);
                        }
                    } else {
                        global $logger;
                        $logger->logError(
                            $this->plan->planID,
                            "(1) Failed to close expired target with ID: " . $query->id
                        );
                    }
                } else {
                    sql_insert(
                        BotDatabaseTable::BOT_TARGETED_MESSAGE_MESSAGES,
                        array(
                            "target_creation_id" => $query->target_creation_id,
                            "user_id" => $message->author->id,
                            "message_id" => $message->id,
                            "message_content" => $message->content,
                            "creation_date" => get_current_date(),
                        )
                    );
                }
            }
        }
    }

    // Separator

    public function closeByID(int|string $targetID, int|string $userID, ?string $reason = null): ?string
    {
        set_sql_cache("1 second");
        $query = get_sql_query(
            BotDatabaseTable::BOT_TARGETED_MESSAGE_CREATIONS,
            array("id", "server_id", "channel_id", "created_thread_id",
                "deletion_date", "target_id"),
            array(
                array("target_creation_id", $targetID),
            ),
            null,
            1
        );

        if (empty($query)) {
            return "Not found";
        } else {
            $query = $query[0];

            if ($query->deletion_date !== null) {
                return "Already closed";
            } else {
                try {
                    if (set_sql_query(
                        BotDatabaseTable::BOT_TARGETED_MESSAGE_CREATIONS,
                        array(
                            "deletion_date" => get_current_date(),
                            "deletion_reason" => empty($reason) ? null : $reason,
                            "deleted_by" => $userID
                        ),
                        array(
                            array("id", $query->id)
                        ),
                        null,
                        1
                    )) {
                        $channel = $this->plan->discord->getChannel($query->channel_id);

                        if ($channel !== null
                            && $channel->guild_id == $query->server_id) {
                            if ($query->created_thread_id !== null) {
                                $this->plan->utilities->deleteThread(
                                    $channel,
                                    $query->created_thread_id,
                                    empty($reason) ? null : $userID . ": " . $reason
                                );
                            } else {
                                $channel->guild->channels->delete(
                                    $channel,
                                    empty($reason) ? null : $userID . ": " . $reason
                                );
                            }
                        }
                        $this->initiate($query->target_id);
                        return null;
                    } else {
                        return "Database query failed";
                    }
                } catch (Throwable $exception) {
                    global $logger;
                    $logger->logError($this->plan->planID, $exception->getMessage());
                    return "(Exception) " . $exception->getMessage();
                }
            }
        }
    }

    public function closeByChannelOrThread(Channel $channel, int|string $userID, ?string $reason = null): ?string
    {
        set_sql_cache("1 second");
        $query = get_sql_query(
            BotDatabaseTable::BOT_TARGETED_MESSAGE_CREATIONS,
            array("id", "created_thread_id", "deletion_date", "target_id"),
            array(
                array("server_id", $channel->guild_id),
                array("channel_id", $channel->id),
            ),
            null,
            1
        );

        if (empty($query)) {
            return "Not found";
        } else {
            $query = $query[0];

            if ($query->deletion_date !== null) {
                return "Already closed";
            } else {
                try {
                    if (set_sql_query(
                        BotDatabaseTable::BOT_TARGETED_MESSAGE_CREATIONS,
                        array(
                            "deletion_date" => get_current_date(),
                            "deletion_reason" => empty($reason) ? null : $reason,
                            "deleted_by" => $userID
                        ),
                        array(
                            array("id", $query->id)
                        ),
                        null,
                        1
                    )) {
                        if ($query->created_thread_id !== null) {
                            $this->plan->utilities->deleteThread(
                                $channel,
                                $query->created_thread_id,
                                empty($reason) ? null : $userID . ": " . $reason
                            );
                        } else {
                            $channel->guild->channels->delete(
                                $channel,
                                empty($reason) ? null : $userID . ": " . $reason
                            );
                        }
                        $this->initiate($query->target_id);
                        return null;
                    } else {
                        return "Database query failed";
                    }
                } catch (Throwable $exception) {
                    global $logger;
                    $logger->logError($this->plan->planID, $exception->getMessage());
                    return "(Exception) " . $exception->getMessage();
                }
            }
        }
    }

    private function closeOldest(object $target): bool
    {
        $query = get_sql_query(
            BotDatabaseTable::BOT_TARGETED_MESSAGE_CREATIONS,
            array("id", "server_id", "channel_id", "created_thread_id"),
            array(
                array("deletion_date", null),
                array("expired", null),
                array("target_id", $target->target_id),
            ),
            array(
                "ASC",
                "creation_date"
            ),
            1
        );

        if (!empty($query)) {
            $query = $query[0];

            if (set_sql_query(
                BotDatabaseTable::BOT_TARGETED_MESSAGE_CREATIONS,
                array(
                    "deletion_date" => get_current_date(),
                ),
                array(
                    array("id", $query->id)
                ),
                null,
                1
            )) {
                $channel = $this->plan->discord->getChannel($query->channel_id);

                if ($channel !== null
                    && $channel->guild_id == $query->server_id) {
                    if ($query->created_thread_id !== null) {
                        $this->plan->utilities->deleteThread(
                            $channel,
                            $query->created_thread_id
                        );
                    } else {
                        $channel->guild->channels->delete($channel);
                    }
                }
                return true;
            } else {
                global $logger;
                $logger->logError($this->plan->planID, "Failed to close oldest target with ID: " . $query->id);
            }
        }
        return false;
    }

    // Separator

    public function getSingle(int|string $targetID): ?object
    {
        $cacheKey = array(__METHOD__, $this->plan->planID, $targetID);
        $cache = get_key_value_pair($cacheKey);

        if ($cache !== null) {
            return $cache === false ? null : $cache;
        } else {
            $query = get_sql_query(
                BotDatabaseTable::BOT_TARGETED_MESSAGE_CREATIONS,
                null,
                array(
                    array("target_creation_id", $targetID),
                ),
                null,
                1
            );

            if (!empty($query)) {
                $query = $query[0];
                $query->target = get_sql_query(
                    BotDatabaseTable::BOT_TARGETED_MESSAGES,
                    null,
                    array(
                        array("id", $query->target_id),
                    ),
                    null,
                    1
                )[0];
                $query->messages = get_sql_query(
                    BotDatabaseTable::BOT_TARGETED_MESSAGE_MESSAGES,
                    null,
                    array(
                        array("target_creation_id", $targetID),
                        array("deletion_date", null)
                    ),
                    array(
                        "DESC",
                        "id"
                    )
                );
                rsort($query->messages);
                set_key_value_pair($cacheKey, $query, self::REFRESH_TIME);
                return $query;
            } else {
                set_key_value_pair($cacheKey, false, self::REFRESH_TIME);
                return null;
            }
        }
    }

    public function getMultiple(int|string $userID, int|string|null $pastLookup = null, ?int $limit = null,
                                bool       $messages = true): array
    {
        $cacheKey = array(__METHOD__, $this->plan->planID, $userID, $pastLookup, $limit, $messages);
        $cache = get_key_value_pair($cacheKey);

        if ($cache !== null) {
            return $cache;
        } else {
            $query = get_sql_query(
                BotDatabaseTable::BOT_TARGETED_MESSAGE_CREATIONS,
                null,
                array(
                    array("user_id", $userID),
                    $pastLookup === null ? "" : array("creation_date", ">", get_past_date($pastLookup)),
                ),
                array(
                    "DESC",
                    "id"
                ),
                $limit
            );

            if (!empty($query)) {
                foreach ($query as $row) {
                    $row->target = get_sql_query(
                        BotDatabaseTable::BOT_TARGETED_MESSAGES,
                        null,
                        array(
                            array("id", $row->target_id),
                        ),
                        null,
                        1
                    )[0];
                    if ($messages) {
                        $row->messages = get_sql_query(
                            BotDatabaseTable::BOT_TARGETED_MESSAGE_MESSAGES,
                            null,
                            array(
                                array("target_creation_id", $row->target_creation_id),
                                array("deletion_date", null)
                            ),
                            array(
                                "DESC",
                                "id"
                            )
                        );
                        rsort($row->messages);
                    }
                }
            }
            set_key_value_pair($cacheKey, $query, self::REFRESH_TIME);
            return $query;
        }
    }

    // Separator

    public function loadSingleTargetMessage(object $target): MessageBuilder
    {
        $this->checkExpired();
        $messageBuilder = MessageBuilder::new();
        $messageBuilder->setContent("Showing target with ID **" . $target->target_creation_id . "**");

        $embed = new Embed($this->plan->discord);
        $embed->setAuthor($this->plan->utilities->getUsername($target->user_id));

        if (!empty($target->target->title)) {
            $embed->setTitle($target->target->title);
        }
        $embed->setDescription($target->deletion_date === null
            ? ($target->expiration_date !== null && get_current_date() > $target->expiration_date
                ? "Expired on " . get_full_date($target->expiration_date)
                : "Open")
            : "Closed on " . get_full_date($target->deletion_date));
        $messageBuilder->addEmbed($embed);

        if (!empty($target->messages)) {
            $max = DiscordInheritedLimits::MAX_EMBEDS_PER_MESSAGE;

            foreach (array_chunk($target->messages, DiscordInheritedLimits::MAX_EMBEDS_PER_MESSAGE) as $chunk) {
                $embed = new Embed($this->plan->discord);

                foreach ($chunk as $message) {
                    $embed->addFieldValues(
                        $this->plan->utilities->getUsername($message->user_id)
                        . " | " . $message->creation_date,
                        DiscordSyntax::HEAVY_CODE_BLOCK . $message->message_content . DiscordSyntax::HEAVY_CODE_BLOCK
                    );
                }
                $messageBuilder->addEmbed($embed);
                $max--;

                if ($max === 0) {
                    break;
                }
            }
        }
        return $messageBuilder;
    }

    public function loadTargetsMessage(int|string $userID, array $targets): MessageBuilder
    {
        $this->checkExpired();
        $date = get_current_date();
        $messageBuilder = MessageBuilder::new();
        $messageBuilder->setContent("Showing last **" . sizeof($targets) . " targets** of user **" . $this->plan->utilities->getUsername($userID) . "**");

        foreach ($targets as $target) {
            $embed = new Embed($this->plan->discord);

            if (!empty($target->target->title)) {
                $embed->setTitle($target->target->title);
            }
            $embed->setDescription("ID: " . $target->target_creation_id . " | "
                . ($target->deletion_date === null
                    ? ($target->expiration_date !== null && $date > $target->expiration_date
                        ? "Expired on " . get_full_date($target->expiration_date)
                        : "Open")
                    : "Closed on " . get_full_date($target->deletion_date)));
            $messageBuilder->addEmbed($embed);
        }
        return $messageBuilder;
    }

    // Separator

    private function hasMaxOpen(int|string $targetID, int|string|null $userID, int|string $limit): bool
    {
        var_dump(sizeof(get_sql_query(
            BotDatabaseTable::BOT_TARGETED_MESSAGE_CREATIONS,
            array("id"),
            array(
                array("target_id", $targetID),
                $userID === null ? "" : array("user_id", $userID),
                array("deletion_date", null),
            ),
            array(
                "DESC",
                "id"
            ),
            $limit
        )));
        return sizeof(get_sql_query(
                BotDatabaseTable::BOT_TARGETED_MESSAGE_CREATIONS,
                array("id"),
                array(
                    array("target_id", $targetID),
                    $userID === null ? "" : array("user_id", $userID),
                    array("deletion_date", null),
                    null,
                    array("expiration_date", "IS", null, 0),
                    array("expiration_date", ">", get_current_date()),
                    null
                ),
                array(
                    "DESC",
                    "id"
                ),
                $limit
            )) == $limit;
    }

    private function checkExpired(): int
    {
        $counter = 0;
        $query = get_sql_query(
            BotDatabaseTable::BOT_TARGETED_MESSAGE_CREATIONS,
            null,
            array(
                array("deletion_date", null),
                array("expired", null),
                array("expiration_date", "IS NOT", null),
                array("expiration_date", ">", get_current_date())
            ),
            array(
                "DESC",
                "id"
            ),
            100 // Limit so we don't ping Discord too much
        );

        if (!empty($query)) {
            foreach ($query as $row) {
                if (set_sql_query(
                    BotDatabaseTable::BOT_TARGETED_MESSAGE_CREATIONS,
                    array(
                        "expired" => 1
                    ),
                    array(
                        array("id", $row->id)
                    ),
                    null,
                    1
                )) {
                    $counter++;
                    $channel = $this->plan->discord->getChannel($row->channel_id);

                    if ($channel !== null
                        && $channel->guild_id == $row->server_id) {
                        if ($row->created_thread_id !== null) {
                            $this->plan->utilities->deleteThread(
                                $channel,
                                $row->created_thread_id
                            );
                        } else {
                            $channel->guild->channels->delete($channel);
                        }
                    }
                } else {
                    global $logger;
                    $logger->logError(
                        $this->plan->planID,
                        "(2) Failed to close expired target with ID: " . $row->id
                    );
                }
            }
        }
        return $counter;
    }
}