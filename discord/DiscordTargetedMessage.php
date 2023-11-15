<?php

use Discord\Builders\MessageBuilder;
use Discord\Parts\Channel\Channel;
use Discord\Parts\Channel\Message;
use Discord\Parts\Embed\Embed;
use Discord\Parts\User\Member;

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
        $this->checkExpired();
    }

    private function initiate(Member $member): void
    {
        $date = get_current_date(); // Always first
        $this->checkExpired();
        $object = $this->plan->instructions->getObject(
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
        );

        if ($query->max_open_per_user !== null
            && $this->hasMaxOpen($query->id, $interaction->user->id, $query->max_open_per_user)
            || $query->max_open_general !== null
            && $this->hasMaxOpen($query->id, null, $query->max_open_general)) {
            if ($query->max_open_message !== null) {
                $this->plan->utilities->acknowledgeMessage(
                    $interaction,
                    MessageBuilder::new()->setContent(
                        $this->plan->instructions->replace(array($query->max_open_message), $object)[0]
                    ),
                    $query->ephemeral_user_response !== null
                );
            } else {
                $interaction->acknowledge();
            }
            return;
        }

        // Separator
        $message = MessageBuilder::new()->setContent($query->inception_message);

        // Separator
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
                    "server_id" => $interaction->guild_id,
                    "channel_id" => $interaction->channel_id,
                    "user_id" => $interaction->user->id,
                    "creation_date" => $date
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
                            "id" => $interaction->user->id,
                            "type" => "member",
                            "allow" => $query->allow_permission,
                            "deny" => $query->allow_permission
                        )
                    );
                    $this->plan->utilities->createChannel(
                        $interaction->guild,
                        Channel::TYPE_TEXT,
                        $query->create_channel_category_id,
                        (empty($query->create_channel_name)
                            ? $this->plan->utilities->getUsername($interaction->user->id)
                            : $query->create_channel_name)
                        . "-" . $targetID,
                        $query->create_channel_topic,
                        $rolePermissions,
                        $memberPermissions
                    )->done(function (Channel $channel) use ($targetID, $insert, $interaction, $message) {
                        $insert["created_channel_id"] = $channel->id;
                        $insert["created_channel_server_id"] = $channel->guild_id;

                        if (sql_insert(BotDatabaseTable::BOT_TARGETED_MESSAGE_CREATIONS, $insert)) {
                            $channel->sendMessage($message);
                        } else {
                            global $logger;
                            $logger->logError(
                                $this->plan->planID,
                                "(1) Failed to insert target creation of user: " . $interaction->user->id
                            );
                        }
                    });
                } else {
                    //todo thread
                }
                break;
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
                array("id", "target_creation_id", "expiration_date"),
                array(
                    array("created_channel_server_id", $channel->guild_id),
                    array("created_channel_id", $channel->id),
                ),
                null,
                1
            );

            if (!empty($query)) {
                $query = $query[0];

                if ($query->deletion_date !== null) {
                    $channel->guild->channels->delete($channel);
                } else if ($query->expiration_date !== null
                    && get_current_date() > $query->expiration_date) {
                    set_sql_query(
                        BotDatabaseTable::BOT_TARGETED_MESSAGE_CREATIONS,
                        array(
                            "expired" => 1
                        ),
                        array(
                            array("id", $query->id)
                        ),
                        null,
                        1
                    );
                    $channel->guild->channels->delete($channel);
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
            array("id", "created_channel_id", "created_channel_server_id", "deletion_date"),
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
                        $channel = $this->plan->discord->getChannel($query->created_channel_id);

                        if ($channel !== null
                            && $channel->guild_id == $query->created_channel_server_id) {
                            $channel->guild->channels->delete(
                                $channel,
                                empty($reason) ? null : $userID . ": " . $reason
                            );
                        }
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

    public function closeByChannel(Channel $channel, int|string $userID, ?string $reason = null): ?string
    {
        set_sql_cache("1 second");
        $query = get_sql_query(
            BotDatabaseTable::BOT_TARGETED_MESSAGE_CREATIONS,
            array("id", "deletion_date"),
            array(
                array("created_channel_server_id", $channel->guild_id),
                array("created_channel_id", $channel->id),
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
                        $channel->guild->channels->delete(
                            $channel,
                            empty($reason) ? null : $userID . ": " . $reason
                        );
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
        set_sql_cache(self::REFRESH_TIME, self::class);
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

    private function checkExpired(): void
    {
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
                set_sql_query(
                    BotDatabaseTable::BOT_TARGETED_MESSAGE_CREATIONS,
                    array(
                        "expired" => 1
                    ),
                    array(
                        array("id", $row->id)
                    ),
                    null,
                    1
                );
                $channel = $this->plan->discord->getChannel($row->created_channel_id);

                if ($channel !== null
                    && $channel->guild_id == $row->created_channel_server_id) {
                    $channel->guild->channels->delete($channel);
                }
            }
        }
    }
}