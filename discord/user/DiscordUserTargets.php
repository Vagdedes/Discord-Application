<?php

use Discord\Builders\MessageBuilder;
use Discord\Parts\Channel\Channel;
use Discord\Parts\Channel\Message;
use Discord\Parts\Embed\Embed;
use Discord\Parts\Thread\Thread;

class DiscordUserTargets
{
    private DiscordPlan $plan;
    private array $targets;
    public int $ignoreChannelDeletion, $ignoreThreadDeletion;

    private const REFRESH_TIME = "15 seconds";

    public function __construct(DiscordPlan $plan)
    {
        $this->plan = $plan;
        $this->ignoreChannelDeletion = 0;
        $this->ignoreThreadDeletion = 0;
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

        foreach ($this->targets as $arrayKey => $target) {
            unset($this->targets[$arrayKey]);
            $query = get_sql_query(
                BotDatabaseTable::BOT_TARGETED_MESSAGE_INSTRUCTIONS,
                array("instruction_id"),
                array(
                    array("target_id", $target->id),
                    array("deletion_date", null),
                    null,
                    array("expiration_date", "IS", null, 0),
                    array("expiration_date", ">", get_current_date()),
                    null
                )
            );
            $target->instructions = array();

            if (!empty($query)) {
                foreach ($query as $arrayChildKey => $row) {
                    $target->instructions[$arrayChildKey] = $row->instruction_id;
                }
            }
            $this->targets[$target->id] = $target;
            $this->initiate($target);
        }
    }

    private function initiate(object|string|int $query): void
    {
        if (!is_object($query)) {
            if (!empty($this->targets)) {
                foreach ($this->targets as $target) {
                    if ($target->id == $query) {
                        $this->initiate($target);
                        break;
                    }
                }
            }
            return;
        } else {
            $this->checkExpired();

            if ($query->max_open !== null
                && $this->hasMaxOpen($query->id, $query->max_open)
                && ($query->close_oldest_if_max_open === null || !$this->closeOldest($query))) {
                return;
            }
        }
        $members = null;

        foreach ($this->plan->bot->discord->guilds as $guild) {
            if ($guild->id == $query->server_id) {
                $members = $guild->members;
                break;
            }
        }

        if (empty($members)) {
            return;
        } else {
            $members = $members->toArray();
            unset($members[$this->plan->bot->botID]);
            $removalList = get_sql_query(
                BotDatabaseTable::BOT_TARGETED_MESSAGE_CREATIONS,
                array("user_id"),
                array(
                    array("target_id", $query->id),
                    array("creation_date", ">", get_past_date(
                        $query->cooldown_duration !== null ? $query->cooldown_duration : "1 minute"
                    ), 0),
                    array("deletion_date", null),
                    array("expired", null),
                )
            );

            if (!empty($removalList)) {
                foreach ($removalList as $removal) {
                    unset($members[$removal->user_id]);
                }
            }
        }

        if (empty($members)) {
            return;
        }
        $date = get_current_date(); // Always first

        for ($i = 0; $i < min(sizeof($members) + 1, DiscordPredictedLimits::RAPID_CHANNEL_MODIFICATIONS); $i++) {
            $key = array_rand($members);
            $member = $members[$key];
            unset($members[$key]);

            while (true) {
                $targetID = random_number(19);

                if (empty(get_sql_query(
                    BotDatabaseTable::BOT_TARGETED_MESSAGE_CREATIONS,
                    array("target_creation_id"),
                    array(
                        array("target_creation_id", $targetID)
                    ),
                    null,
                    1
                ))) {
                    $insert = array(
                        "plan_id" => $this->plan->planID,
                        "target_id" => $query->id,
                        "target_creation_id" => $targetID,
                        "user_id" => $member->user->id,
                        "server_id" => $query->server_id,
                        "creation_date" => $date,
                        "expiration_date" => get_future_date($query->target_duration),
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
                                    "deny" => empty($role->deny) ? $query->deny_permission : $role->deny
                                );
                            }
                        }
                        $memberPermissions = array(
                            array(
                                "id" => $member->user->id,
                                "type" => "member",
                                "allow" => $query->allow_permission,
                                "deny" => $query->deny_permission
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
                        )->done(function (Channel $channel)
                        use ($targetID, $insert, $member, $query) {
                            $insert["channel_id"] = $channel->id;

                            if (sql_insert(BotDatabaseTable::BOT_TARGETED_MESSAGE_CREATIONS, $insert)) {
                                $this->plan->channels->addTemporary($channel, array(
                                    "message_retention" => "1 minute",
                                    "message_cooldown" => 1,
                                    "failure_message" => $query->failure_message,
                                    "cooldown_message" => $query->cooldown_message,
                                    "prompt_message" => $query->prompt_message,
                                    "instructions" => $query->instructions
                                ));
                                $message = MessageBuilder::new()->setContent(
                                    $this->plan->instructions->replace(
                                        array($query->create_message),
                                        $this->plan->instructions->getObject(
                                            $member->guild,
                                            $channel,
                                            null,
                                            $member
                                        )
                                    )[0]
                                );
                                $channel->sendMessage($message);
                            } else {
                                global $logger;
                                $logger->logError(
                                    $this->plan->planID,
                                    "(1) Failed to insert target creation with ID: " . $query->id
                                );
                            }
                        });
                    } else if ($query->create_channel_id !== null) {
                        $channel = $this->plan->bot->discord->getChannel($query->create_channel_id);

                        if ($channel !== null
                            && $channel->allowText()
                            && $channel->guild_id == $query->server_id) {
                            $message = MessageBuilder::new()->setContent(
                                $this->plan->instructions->replace(
                                    array($query->create_message),
                                    $this->plan->instructions->getObject(
                                        $member->guild,
                                        $channel,
                                        null,
                                        $member
                                    )
                                )[0]
                            );

                            $channel->startThread($message, $targetID)->done(function (Thread $thread)
                            use ($insert, $member, $channel, $message, $query) {
                                $insert["channel_id"] = $channel->id;
                                $insert["created_thread_id"] = $thread->id;

                                if (sql_insert(BotDatabaseTable::BOT_TARGETED_MESSAGE_CREATIONS, $insert)) {
                                    $channel->sendMessage($message);
                                } else {
                                    global $logger;
                                    $logger->logError(
                                        $this->plan->planID,
                                        "(2) Failed to insert target creation with ID: " . $query->id
                                    );
                                }
                            });
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

    public function track(Message $message): bool
    {
        if (strlen($message->content) > 0) {
            $channel = $message->channel;
            set_sql_cache("1 second");
            $query = get_sql_query(
                BotDatabaseTable::BOT_TARGETED_MESSAGE_CREATIONS,
                array("id", "target_id", "target_creation_id", "created_thread_id",
                    "expiration_date", "deletion_date"),
                array(
                    array("server_id", $channel->guild_id),
                    array("channel_id", $channel->id),
                ),
                array(
                    "DESC",
                    "id"
                ),
                1
            );

            if (!empty($query)) {
                $query = $query[0];

                if ($query->deletion_date !== null) {
                    if ($query->created_thread_id !== null) {
                        $this->ignoreThreadDeletion++;
                        $this->plan->utilities->deleteThread(
                            $channel,
                            $query->created_thread_id
                        );
                    } else {
                        $this->ignoreChannelDeletion++;
                        $channel->guild->channels->delete($channel);
                    }
                    $this->initiate($query->target_id);
                    return true;
                } else if (get_current_date() > $query->expiration_date) {
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
                            $this->ignoreThreadDeletion++;
                            $this->plan->utilities->deleteThread(
                                $channel,
                                $query->created_thread_id
                            );
                        } else {
                            $this->ignoreChannelDeletion++;
                            $channel->guild->channels->delete($channel);
                        }
                        $this->initiate($query->target_id);
                    } else {
                        global $logger;
                        $logger->logError(
                            $this->plan->planID,
                            "(1) Failed to close expired target with ID: " . $query->id
                        );
                    }
                    return true;
                }
            }
        }
        return false;
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
                        $channel = $this->plan->bot->discord->getChannel($query->channel_id);

                        if ($channel !== null
                            && $channel->allowText()
                            && $channel->guild_id == $query->server_id) {
                            if ($query->created_thread_id !== null) {
                                $this->ignoreThreadDeletion++;
                                $this->plan->utilities->deleteThread(
                                    $channel,
                                    $query->created_thread_id,
                                    empty($reason) ? null : $userID . ": " . $reason
                                );
                            } else {
                                $this->ignoreChannelDeletion++;
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

    public function closeByChannelOrThread(Channel         $channel,
                                           int|string|null $userID = null,
                                           ?string         $reason = null,
                                           bool            $delete = true): ?string
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
                        if ($delete) {
                            if ($query->created_thread_id !== null) {
                                $this->ignoreThreadDeletion++;
                                $this->plan->utilities->deleteThread(
                                    $channel,
                                    $query->created_thread_id,
                                    empty($reason) ? null : $userID . ": " . $reason
                                );
                            } else {
                                $this->ignoreChannelDeletion++;
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

    private function closeOldest(object $target): bool
    {
        $query = get_sql_query(
            BotDatabaseTable::BOT_TARGETED_MESSAGE_CREATIONS,
            array("id", "server_id", "channel_id", "created_thread_id"),
            array(
                array("deletion_date", null),
                array("expired", null),
                array("target_id", $target->id),
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
                $channel = $this->plan->bot->discord->getChannel($query->channel_id);

                if ($channel !== null
                    && $channel->allowText()
                    && $channel->guild_id == $query->server_id) {
                    if ($query->created_thread_id !== null) {
                        $this->ignoreThreadDeletion++;
                        $this->plan->utilities->deleteThread(
                            $channel,
                            $query->created_thread_id
                        );
                    } else {
                        $this->ignoreChannelDeletion++;
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
                $query->target = $this->targets[$query->target_id];
                $query->messages = $this->plan->aiMessages->getConversation(
                    $query->server_id,
                    $query->created_thread_id === null ? $query->channel_id : null,
                    $query->created_thread_id === null ? null : $query->created_thread_id,
                    $query->user_id
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

    public function getMultiple(int|string      $serverID, int|string $userID,
                                int|string|null $pastLookup = null, ?int $limit = null,
                                bool            $messages = true): array
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
                    array("server_id", $serverID),
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
                    $row->target = $this->targets[$row->target_id];

                    if ($messages) {
                        $row->messages = $this->plan->aiMessages->getConversation(
                            $row->server_id,
                            $row->created_thread_id === null ? $row->channel_id : null,
                            $row->created_thread_id === null ? null : $row->created_thread_id,
                            $row->user_id
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
        $this->initiate($target->target_id);
        $messageBuilder = MessageBuilder::new();
        $messageBuilder->setContent("Showing target with ID **" . $target->target_creation_id . "**");

        $embed = new Embed($this->plan->bot->discord);
        $user = $this->plan->utilities->getUser($target->user_id);

        if ($user !== null) {
            $embed->setAuthor($user->id, $user->avatar);
        } else {
            $embed->setAuthor($target->user_id);
        }
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
                $embed = new Embed($this->plan->bot->discord);

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
            $embed = new Embed($this->plan->bot->discord);

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

    private function hasMaxOpen(int|string $targetID, int|string $limit): bool
    {
        return sizeof(get_sql_query(
                BotDatabaseTable::BOT_TARGETED_MESSAGE_CREATIONS,
                array("id"),
                array(
                    array("target_id", $targetID),
                    array("deletion_date", null),
                    array("expired", null),
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

    private function checkExpired(): void
    {
        $query = get_sql_query(
            BotDatabaseTable::BOT_TARGETED_MESSAGE_CREATIONS,
            null,
            array(
                array("plan_id", $this->plan->planID),
                array("deletion_date", null),
                array("expired", null),
                array("expiration_date", "<", get_current_date())
            ),
            array(
                "DESC",
                "id"
            ),
            DiscordPredictedLimits::RAPID_CHANNEL_MODIFICATIONS // Limit so we don't ping Discord too much
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
                    $channel = $this->plan->bot->discord->getChannel($row->channel_id);

                    if ($channel !== null
                        && $channel->allowText()
                        && $channel->guild_id == $row->server_id) {
                        if ($row->created_thread_id !== null) {
                            $this->ignoreThreadDeletion++;
                            $this->plan->utilities->deleteThread(
                                $channel,
                                $row->created_thread_id
                            );
                        } else {
                            $this->ignoreChannelDeletion++;
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
    }

}