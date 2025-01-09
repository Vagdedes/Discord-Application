<?php

use Discord\Builders\MessageBuilder;
use Discord\Helpers\Collection;
use Discord\Parts\Channel\Channel;
use Discord\Parts\Channel\Message;
use Discord\Parts\Embed\Embed;
use Discord\Parts\Interactions\Interaction;

class DiscordUserTickets
{
    private DiscordBot $bot;
    public int $ignoreDeletion;
    private array $tickets;
    private array $abstractTickets;

    public function __construct(DiscordBot $bot)
    {
        $this->bot = $bot;
        $this->tickets = array();
        $this->ignoreDeletion = 0;
        $tickets = get_sql_query(
            BotDatabaseTable::BOT_TICKETS,
            null,
            array(
                array("modal_component_id", "IS NOT", null), // Attention
                array("deletion_date", null),
                null,
                array("expiration_date", "IS", null, 0),
                array("expiration_date", ">", get_current_date()),
                null
            )
        );

        if (!empty($tickets)) {
            foreach ($tickets as $ticket) {
                $this->tickets[$ticket->id] = $ticket;
                $this->tickets[$ticket->name] = $ticket;
            }
        }
        $tickets = get_sql_query(
            BotDatabaseTable::BOT_TICKETS,
            null,
            array(
                array("modal_component_id", "IS", null), // Attention
                array("deletion_date", null),
                null,
                array("expiration_date", "IS", null, 0),
                array("expiration_date", ">", get_current_date()),
                null
            )
        );

        if (!empty($tickets)) {
            foreach ($tickets as $ticket) {
                $this->abstractTickets[$ticket->id] = $ticket;
            }
        }
        $this->checkExpired();
    }

    public function call(Interaction $interaction, string $key): bool
    {
        $query = $this->tickets[$key] ?? null;

        if (!empty($query)) {
            $query = $query[0];
            return $this->bot->component->showModal(
                $interaction,
                $query->modal_component_id,
                function (Interaction $interaction, Collection $components) use ($query) {
                    $this->store($interaction, $components, $query);
                }
            );
        } else {
            global $logger;
            $logger->logError("Ticket not found with key: " . $key);
            return false;
        }
    }

    public function create(Interaction     $interaction,
                           ?MessageBuilder $message,
                           int|string|null $categoryID, string $channelName, ?string $channelTopic,
                           int|string|null $memberAllowPermissions, int|string|null $memberDenyPermissions,
                           ?array          $rolePermissions,
                           int|string|null $duration): void
    {
        $date = get_current_date(); // Always first
        $this->checkExpired();

        while (true) {
            $ticketID = random_number(19);

            if (empty(get_sql_query(
                BotDatabaseTable::BOT_TICKET_CREATIONS,
                array("ticket_creation_id"),
                array(
                    array("ticket_creation_id", $ticketID)
                ),
                null,
                1
            ))) {
                $insert = array(
                    "ticket_id" => null,
                    "ticket_creation_id" => $ticketID,
                    "server_id" => $interaction->guild_id,
                    "channel_id" => $interaction->channel_id,
                    "user_id" => $interaction->user->id,
                    "creation_date" => $date,
                    "deletion_date" => $duration !== null ? get_future_date($duration) : null
                );

                if (!empty($rolePermissions)) {
                    foreach ($rolePermissions as $arrayKey => $role) {
                        $rolePermissions[$arrayKey] = array(
                            "id" => $arrayKey,
                            "type" => "role",
                            "allow" => $role->allow,
                            "deny" => $role->deny
                        );
                    }
                }
                if (empty($memberAllowPermissions)
                    && empty($memberDenyPermissions)) {
                    $memberPermissions = array();
                } else {
                    $memberPermissions = array(
                        array(
                            "id" => $interaction->user->id,
                            "type" => "member",
                            "allow" => empty($memberAllowPermissions) ? 0 : $memberAllowPermissions,
                            "deny" => empty($memberDenyPermissions) ? 0 : $memberDenyPermissions
                        )
                    );
                }
                $this->bot->utilities->createChannel(
                    $interaction->guild,
                    Channel::TYPE_TEXT,
                    $categoryID,
                    $channelName,
                    $channelTopic,
                    $rolePermissions,
                    $memberPermissions
                )?->done(function (Channel $channel)
                use ($ticketID, $insert, $interaction, $message) {
                    $insert["created_channel_id"] = $channel->id;
                    $insert["created_channel_server_id"] = $channel->guild_id;

                    if (sql_insert(BotDatabaseTable::BOT_TICKET_CREATIONS, $insert)) {
                        if ($message !== null) {
                            $channel->sendMessage($message);
                        }
                    } else {
                        global $logger;
                        $logger->logError(
                            "Failed to insert abstract ticket."
                        );
                    }
                });
                break;
            }
        }
    }

    private function store(Interaction $interaction, Collection $components,
                           object      $query): void
    {
        $date = get_current_date(); // Always first
        $this->checkExpired();
        $object = $this->bot->instructions->getObject(
            $interaction->guild,
            $interaction->channel,
            $interaction->user,
            $interaction->message,
        );

        if ($query->cooldown_time !== null
            && $this->hasCooldown($query->id, $interaction->user->id, $query->cooldown_time)) {
            if ($query->cooldown_message !== null) {
                $this->bot->utilities->acknowledgeMessage(
                    $interaction,
                    MessageBuilder::new()->setContent(
                        $this->bot->instructions->replace(array($query->cooldown_message), $object)[0]
                    ),
                    $query->ephemeral_user_response !== null
                );
            } else {
                $interaction->acknowledge();
            }
            return;
        }
        if ($query->max_open_per_user !== null
            && $this->hasMaxOpen($query->id, $interaction->user->id, $query->max_open_per_user)
            || $query->max_open_general !== null
            && $this->hasMaxOpen($query->id, null, $query->max_open_general)) {
            if ($query->max_open_message !== null) {
                $this->bot->utilities->acknowledgeMessage(
                    $interaction,
                    MessageBuilder::new()->setContent(
                        $this->bot->instructions->replace(array($query->max_open_message), $object)[0]
                    ),
                    $query->ephemeral_user_response !== null
                );
            } else {
                $interaction->acknowledge();
            }
            return;
        }
        if ($query->user_response !== null) {
            $this->bot->utilities->acknowledgeMessage(
                $interaction,
                MessageBuilder::new()->setContent(
                    $this->bot->instructions->replace(array($query->user_response), $object)[0]
                ),
                $query->ephemeral_user_response !== null
            );
        } else {
            $this->bot->listener->callTicketImplementation(
                $interaction,
                $query->listener_class,
                $query->listener_method,
                $components
            );
        }

        // Separator

        $components = $components->toArray();
        $post = $query->post_server_id !== null
            && $query->post_channel_id !== null;
        $create = $query->create_channel_category_id !== null;

        if ($post || $create) {
            $message = MessageBuilder::new();
            $embed = new Embed($this->bot->discord);
            $embed->setAuthor($interaction->user->username, $interaction->user->getAvatarAttribute());
            $embed->setTimestamp(time());

            if ($query->post_title !== null) {
                $embed->setTitle($query->post_title);
            }
            if ($query->post_description !== null) {
                $embed->setDescription($query->post_description);
            }
            if ($query->post_color !== null) {
                $embed->setColor($query->post_color);
            }
            if ($query->post_image_url !== null) {
                $embed->setImage($query->post_image_url);
            }
            foreach ($components as $component) {
                $embed->addFieldValues(
                    strtoupper($component["custom_id"]),
                    DiscordSyntax::HEAVY_CODE_BLOCK . $component["value"] . DiscordSyntax::HEAVY_CODE_BLOCK
                );
            }
            $message->addEmbed($embed);
        }

        if ($post) {
            $channel = $this->bot->discord->getChannel($query->post_channel_id);

            if ($channel !== null
                && $this->bot->utilities->allowText($channel)
                && $channel->guild_id == $query->post_server_id) {
                $channel->sendMessage($message);
            }
        }

        // Separator
        while (true) {
            $ticketID = random_number(19);

            if (empty(get_sql_query(
                BotDatabaseTable::BOT_TICKET_CREATIONS,
                array("ticket_creation_id"),
                array(
                    array("ticket_creation_id", $ticketID)
                ),
                null,
                1
            ))) {
                $insert = array(
                    "ticket_id" => $query->id,
                    "ticket_creation_id" => $ticketID,
                    "server_id" => $interaction->guild_id,
                    "channel_id" => $interaction->channel_id,
                    "user_id" => $interaction->user->id,
                    "creation_date" => $date,
                    "deletion_date" => $post || $create ? null : $date
                );

                if ($create) {
                    $rolePermissions = get_sql_query(
                        BotDatabaseTable::BOT_TICKET_ROLES,
                        array("allow", "deny", "role_id"),
                        array(
                            array("deletion_date", null),
                            array("ticket_id", $query->id)
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
                            "id" => $interaction->user->id,
                            "type" => "member",
                            "allow" => $query->allow_permission,
                            "deny" => $query->deny_permission
                        )
                    );
                    $this->bot->utilities->createChannel(
                        $interaction->guild,
                        Channel::TYPE_TEXT,
                        $query->create_channel_category_id,
                        (empty($query->create_channel_name)
                            ? $this->bot->utilities->getUsername($interaction->user->id)
                            : $query->create_channel_name)
                        . "-" . $ticketID,
                        $query->create_channel_topic,
                        $rolePermissions,
                        $memberPermissions
                    )?->done(function (Channel $channel)
                    use ($components, $ticketID, $insert, $interaction, $message, $query) {
                        $insert["created_channel_id"] = $channel->id;
                        $insert["created_channel_server_id"] = $channel->guild_id;

                        if (sql_insert(BotDatabaseTable::BOT_TICKET_CREATIONS, $insert)) {
                            foreach ($components as $component) {
                                sql_insert(BotDatabaseTable::BOT_TICKET_SUB_CREATIONS,
                                    array(
                                        "ticket_creation_id" => $ticketID,
                                        "input_key" => $component["custom_id"],
                                        "input_value" => $component["value"]
                                    )
                                );
                            }
                            $channel->sendMessage($message);
                        } else {
                            global $logger;
                            $logger->logError(
                                "(1) Failed to insert ticket creation with ID: " . $query->id
                            );
                        }
                    });
                } else {
                    if (sql_insert(BotDatabaseTable::BOT_TICKET_CREATIONS, $insert)) {
                        foreach ($components as $component) {
                            sql_insert(BotDatabaseTable::BOT_TICKET_SUB_CREATIONS,
                                array(
                                    "ticket_creation_id" => $ticketID,
                                    "input_key" => $component["custom_id"],
                                    "input_value" => $component["value"]
                                )
                            );
                        }
                    } else {
                        global $logger;
                        $logger->logError(
                            "(2) Failed to insert ticket creation with ID: " . $query->id
                        );
                    }
                }
                break;
            }
        }
    }

    public function track(Message $message): bool
    {
        if (strlen($message->content) > 0) {
            $channel = $message->channel;
            $query = get_sql_query(
                BotDatabaseTable::BOT_TICKET_CREATIONS,
                array("id", "ticket_id", "ticket_creation_id", "expiration_date"),
                array(
                    array("created_channel_server_id", $channel->guild_id),
                    array("created_channel_id", $channel->id),
                ),
                array(
                    "DESC",
                    "id"
                ),
                1
            );

            if (!empty($query)) {
                $query = $query[0];

                if (array_key_exists($query->ticket_id, $this->tickets)) {
                    if ($query->deletion_date !== null) {
                        $this->ignoreDeletion++;
                        $channel->guild->channels->delete($channel);
                    } else if ($query->expiration_date !== null
                        && get_current_date() > $query->expiration_date) {
                        if (set_sql_query(
                            BotDatabaseTable::BOT_TICKET_CREATIONS,
                            array(
                                "expired" => 1
                            ),
                            array(
                                array("id", $query->id)
                            ),
                            null,
                            1
                        )) {
                            $this->ignoreDeletion++;
                            $channel->guild->channels->delete($channel);
                        } else {
                            global $logger;
                            $logger->logError(
                                "(1) Failed to close expired ticket with ID: " . $query->id
                            );
                        }
                    } else {
                        sql_insert(
                            BotDatabaseTable::BOT_TICKET_MESSAGES,
                            array(
                                "ticket_creation_id" => $query->ticket_creation_id,
                                "user_id" => $message->user_id,
                                "message_id" => $message->id,
                                "message_content" => $message->content,
                                "creation_date" => get_current_date(),
                            )
                        );
                        return true;
                    }
                }
            }
        }
        return false;
    }

    // Separator

    public function closeByID(int|string $ticketID, int|string $userID, ?string $reason = null): ?string
    {
        $query = get_sql_query(
            BotDatabaseTable::BOT_TICKET_CREATIONS,
            array("id", "created_channel_id", "created_channel_server_id", "deletion_date"),
            array(
                array("ticket_creation_id", $ticketID),
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
                        BotDatabaseTable::BOT_TICKET_CREATIONS,
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
                        $channel = $this->bot->discord->getChannel($query->created_channel_id);

                        if ($channel !== null
                            && $this->bot->utilities->allowText($channel)
                            && $channel->guild_id == $query->created_channel_server_id) {
                            $this->ignoreDeletion++;
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
                    $logger->logError($exception->getMessage());
                    return "(Exception) " . $exception->getMessage();
                }
            }
        }
    }

    public function closeByChannel(Channel         $channel,
                                   int|string|null $userID = null,
                                   ?string         $reason = null,
                                   bool            $delete = true): ?string
    {
        $query = get_sql_query(
            BotDatabaseTable::BOT_TICKET_CREATIONS,
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
                        BotDatabaseTable::BOT_TICKET_CREATIONS,
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
                            $this->ignoreDeletion++;
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
                    $logger->logError($exception->getMessage());
                    return "(Exception) " . $exception->getMessage();
                }
            }
        }
    }

    // Separator

    public function getSingle(int|string $ticketID): ?object
    {
        $query = get_sql_query(
            BotDatabaseTable::BOT_TICKET_CREATIONS,
            null,
            array(
                array("ticket_creation_id", $ticketID),
            ),
            null,
            1
        );

        if (!empty($query)) {
            $query = $query[0];
            $query->ticket = get_sql_query(
                BotDatabaseTable::BOT_TICKETS,
                null,
                array(
                    array("id", $query->ticket_id),
                ),
                null,
                1
            )[0];
            $query->key_value_pairs = get_sql_query(
                BotDatabaseTable::BOT_TICKET_SUB_CREATIONS,
                null,
                array(
                    array("ticket_creation_id", $ticketID),
                )
            );
            $query->messages = get_sql_query(
                BotDatabaseTable::BOT_TICKET_MESSAGES,
                null,
                array(
                    array("ticket_creation_id", $ticketID),
                    array("deletion_date", null)
                ),
                array(
                    "DESC",
                    "id"
                )
            );
            rsort($query->messages);
            return $query;
        } else {
            return null;
        }
    }

    public function getMultiple(int|string      $serverID, int|string $userID,
                                int|string|null $pastLookup = null, ?int $limit = null,
                                bool            $messages = true): array
    {
        $query = get_sql_query(
            BotDatabaseTable::BOT_TICKET_CREATIONS,
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
                $row->ticket = get_sql_query(
                    BotDatabaseTable::BOT_TICKETS,
                    null,
                    array(
                        array("id", $row->ticket_id),
                    ),
                    null,
                    1
                )[0];
                $row->key_value_pairs = get_sql_query(
                    BotDatabaseTable::BOT_TICKET_SUB_CREATIONS,
                    null,
                    array(
                        array("ticket_creation_id", $row->ticket_creation_id),
                    )
                );
                if ($messages) {
                    $row->messages = get_sql_query(
                        BotDatabaseTable::BOT_TICKET_MESSAGES,
                        null,
                        array(
                            array("ticket_creation_id", $row->ticket_creation_id),
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
        return $query;
    }

    // Separator

    public function loadSingleTicketMessage(object $ticket): MessageBuilder
    {
        $this->checkExpired();
        $date = get_current_date();
        $messageBuilder = MessageBuilder::new();
        $messageBuilder->setContent("Showing ticket with ID **" . $ticket->ticket_creation_id . "**");

        $embed = new Embed($this->bot->discord);
        $user = $this->bot->utilities->getUser($ticket->user_id);

        if ($user !== null) {
            $embed->setAuthor($user->id, $user->avatar);
        } else {
            $embed->setAuthor($ticket->user_id);
        }
        if (!empty($ticket->ticket->title)) {
            $embed->setTitle($ticket->ticket->title);
        }
        $embed->setDescription($ticket->deletion_date === null
            ? ($ticket->expiration_date !== null && $date > $ticket->expiration_date
                ? "Expired on " . get_full_date($ticket->expiration_date)
                : "Open")
            : "Closed on " . get_full_date($ticket->deletion_date));

        foreach ($ticket->key_value_pairs as $ticketProperties) {
            $embed->addFieldValues(
                strtoupper($ticketProperties->input_key),
                DiscordSyntax::HEAVY_CODE_BLOCK . $ticketProperties->input_value . DiscordSyntax::HEAVY_CODE_BLOCK
            );
            $embed->setTimestamp(strtotime($ticket->creation_date));
        }
        $messageBuilder->addEmbed($embed);

        if (!empty($ticket->messages)) {
            $max = DiscordInheritedLimits::MAX_EMBEDS_PER_MESSAGE - 1; // Minus one due to previous embed

            foreach (array_chunk($ticket->messages, DiscordInheritedLimits::MAX_EMBEDS_PER_MESSAGE) as $chunk) {
                $embed = new Embed($this->bot->discord);

                foreach ($chunk as $message) {
                    $embed->addFieldValues(
                        $this->bot->utilities->getUsername($message->user_id)
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

    public function loadTicketsMessage(int|string $userID, array $tickets): MessageBuilder
    {
        $this->checkExpired();
        $date = get_current_date();
        $messageBuilder = MessageBuilder::new();
        $messageBuilder->setContent("Showing last **" . sizeof($tickets) . " tickets** of user **" . $this->bot->utilities->getUsername($userID) . "**");

        foreach ($tickets as $ticket) {
            $embed = new Embed($this->bot->discord);

            if (!empty($ticket->ticket->title)) {
                $embed->setTitle($ticket->ticket->title);
            }
            $embed->setDescription("ID: " . $ticket->ticket_creation_id . " | "
                . ($ticket->deletion_date === null
                    ? ($ticket->expiration_date !== null && $date > $ticket->expiration_date
                        ? "Expired on " . get_full_date($ticket->expiration_date)
                        : "Open")
                    : "Closed on " . get_full_date($ticket->deletion_date)));

            foreach ($ticket->key_value_pairs as $ticketProperties) {
                $embed->addFieldValues(
                    strtoupper($ticketProperties->input_key),
                    DiscordSyntax::HEAVY_CODE_BLOCK . $ticketProperties->input_value . DiscordSyntax::HEAVY_CODE_BLOCK
                );
                $embed->setTimestamp(strtotime($ticket->creation_date));
            }
            $messageBuilder->addEmbed($embed);
        }
        return $messageBuilder;
    }

    // Separator

    private function hasCooldown(int|string $ticketID, int|string $userID, int|string $pastLookup): bool
    {
        return !empty(get_sql_query(
            BotDatabaseTable::BOT_TICKET_CREATIONS,
            array("id"),
            array(
                array("ticket_id", $ticketID),
                array("user_id", $userID),
                array("creation_date", ">", get_past_date($pastLookup)),
            ),
            array(
                "DESC",
                "id"
            ),
            1
        ));
    }

    private function hasMaxOpen(int|string $ticketID, int|string|null $userID, int|string $limit): bool
    {
        return sizeof(get_sql_query(
                BotDatabaseTable::BOT_TICKET_CREATIONS,
                array("id"),
                array(
                    array("ticket_id", $ticketID),
                    $userID === null ? "" : array("user_id", $userID),
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
            BotDatabaseTable::BOT_TICKET_CREATIONS,
            null,
            array(
                array("deletion_date", null),
                array("expired", null),
                array("expiration_date", "IS NOT", null),
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
                    BotDatabaseTable::BOT_TICKET_CREATIONS,
                    array(
                        "expired" => 1
                    ),
                    array(
                        array("id", $row->id)
                    ),
                    null,
                    1
                )) {
                    $channel = $this->bot->discord->getChannel($row->created_channel_id);

                    if ($channel !== null
                        && $this->bot->utilities->allowText($channel)
                        && $channel->guild_id == $row->created_channel_server_id) {
                        $this->ignoreDeletion++;
                        $channel->guild->channels->delete($channel);
                    }
                } else {
                    global $logger;
                    $logger->logError(
                        "(2) Failed to close expired ticket with ID: " . $row->id
                    );
                }
            }
        }
    }
}