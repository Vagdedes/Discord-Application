<?php

use Discord\Builders\MessageBuilder;
use Discord\Helpers\Collection;
use Discord\Parts\Embed\Embed;
use Discord\Parts\Interactions\Interaction;

class DiscordTicket
{
    private DiscordPlan $plan;

    public function __construct(DiscordPlan $plan)
    {
        $this->plan = $plan;
    }

    public function open(Interaction $interaction, string $key): bool
    {
        set_sql_cache();
        $query = get_sql_query(
            BotDatabaseTable::BOT_TICKETS,
            null,
            array(
                array("deletion_date", null),
                array("name", $key),
                null,
                array("expiration_date", "IS", null, 0),
                array("expiration_date", ">", get_current_date()),
                null
            ),
            null,
            1
        );

        if (!empty($query)) {
            $query = $query[0];
            return $this->plan->component->showModal(
                $interaction,
                $query->modal_component_id,
                function (Interaction $interaction, Collection $components) use ($query) {
                    $this->store($interaction, $components, $query);
                }
            );
        } else {
            global $logger;
            $logger->logError($this->plan->planID, "Ticket not found with key: " . $key);
            return false;
        }
    }

    private function store(Interaction $interaction, Collection $components,
                           object      $query): void
    {
        if ($query->user_response !== null) {
            $object = $this->plan->instructions->getObject(
                $interaction->guild_id,
                $interaction->guild->name,
                $interaction->channel_id,
                $interaction->channel->name,
                $interaction->message?->thread?->id,
                $interaction->message?->thread,
                $interaction->user->id,
                $interaction->user->username,
                $interaction->user->displayname,
                $interaction->message->content,
                $interaction->message->id,
                $this->plan->discord->user->id
            );
            $interaction->acknowledgeWithResponse($query->ephemeral_user_response !== null);
            $interaction->updateOriginalResponse(MessageBuilder::new()->setContent(
                $this->plan->instructions->replace(array($query->user_response), $object)[0]
            ));
        } else {
            $interaction->acknowledge();
        }

        // Separator

        $components = $components->toArray();

        if ($query->post_server_id !== null
            && $query->post_channel_id !== null) {
            $message = MessageBuilder::new();
            $embed = new Embed($this->plan->discord);
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
                    "```" . $component["value"] . "```"
                );
            }
            $message->addEmbed($embed);
            $channel = $this->plan->discord->getChannel($query->post_channel_id);

            if ($channel !== null) {
                $channel->sendMessage($message);
            }
        }

        // Separator
        while (true) {
            $ticketID = random_number(19);

            if (empty(get_sql_query(
                BotDatabaseTable::BOT_TICKET_CREATIONS,
                array("ticket_id"),
                array(
                    array("ticket_creation_id", $ticketID)
                ),
                null,
                1
            ))) {
                if (sql_insert(BotDatabaseTable::BOT_TICKET_CREATIONS,
                    array(
                        "ticket_id" => $query->id,
                        "ticket_creation_id" => $ticketID,
                        "server_id" => $interaction->guild_id,
                        "channel_id" => $interaction->channel_id,
                        "user_id" => $interaction->user->id,
                        "creation_date" => get_current_date(),
                    ))) {
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
                        $this->plan->planID,
                        "Failed to insert ticket creation of user: " . $interaction->user->id
                    );
                }
                break;
            }
        }
    }

    public function get(int|string $userID, int|string|null $pastLookup = null): array
    {
        $cacheKey = array(__METHOD__, $this->plan->planID, $userID, $pastLookup);
        $cache = get_key_value_pair($cacheKey);

        if ($cache !== null) {
            return $cache;
        } else {
            $query = get_sql_query(
                BotDatabaseTable::BOT_TICKET_CREATIONS,
                null,
                array(
                    array("user_id", $userID),
                    array("deletion_date", null),
                    $pastLookup === null ? "" : array("creation_date", ">", get_past_date($pastLookup)),
                ),
                array(
                    "DESC",
                    "id"
                )
            );

            if (!empty($query)) {
                foreach ($query as $row) {
                    $row->key_value_pairs = get_sql_query(
                        BotDatabaseTable::BOT_TICKET_SUB_CREATIONS,
                        null,
                        array(
                            array("ticket_creation_id", $row->ticket_id),
                        )
                    );
                }
            }
            set_key_value_pair($cacheKey, $query, "15 seconds");
            return $query;
        }
    }
}