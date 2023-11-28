<?php

use Discord\Builders\Components\TextInput;
use Discord\Builders\MessageBuilder;
use Discord\Helpers\Collection;
use Discord\Parts\Interactions\Interaction;

class DiscordNotes
{
    private DiscordPlan $plan;

    public function __construct(DiscordPlan $plan)
    {
        $this->plan = $plan;
    }

    public function create(Interaction      $interaction,
                           int|float|string $key, ?string $creationReason = null): void
    {
        if ($this->get($interaction->guild_id, $key, $interaction->user->id) !== null) {
            $this->plan->utilities->acknowledgeMessage(
                $interaction,
                MessageBuilder::new()->setContent(
                    "A note with that key already exists."
                ), true
            );
        } else {
            $this->plan->component->createModal(
                $interaction,
                "Connect Account",
                array(
                    TextInput::new("Title", TextInput::STYLE_SHORT)
                        ->setMinLength(1)->setMaxLength(128)
                        ->setPlaceholder("Give your note a title."),
                    TextInput::new("Description", TextInput::STYLE_PARAGRAPH)
                        ->setMinLength(1)->setMaxLength(2000)
                        ->setPlaceholder("Give your note a description.")
                ),
                null,
                function (Interaction $interaction, Collection $components) use ($key, $creationReason) {
                    if (!$this->plan->component->hasCooldown($interaction)) {
                        $components = $components->toArray();
                        $title = array_shift($components)["title"];
                        $description = array_shift($components)["description"];

                        while (true) {
                            $noteID = random_number(19);

                            if (empty(get_sql_query(
                                BotDatabaseTable::BOT_NOTES,
                                array("note_id"),
                                array(
                                    array("note_id", $noteID)
                                ),
                                null,
                                1
                            ))) {
                                if (sql_insert(
                                        BotDatabaseTable::BOT_NOTES,
                                        array(
                                            "note_id" => $noteID,
                                            "note_key" => $key,
                                            "user_id" => $interaction->user->id,
                                            "creation_date" => get_current_date(),
                                            "creation_reason" => $creationReason
                                        )
                                    )
                                    && sql_insert(
                                        BotDatabaseTable::BOT_NOTE_CHANGES,
                                        array(
                                            "note_id" => $noteID,
                                            "user_id" => $interaction->user->id,
                                            "title" => $title,
                                            "description" => $description,
                                            "creation_date" => get_current_date(),
                                            "creation_reason" => $creationReason
                                        )
                                    )) {
                                    $this->plan->utilities->acknowledgeMessage(
                                        $interaction,
                                        MessageBuilder::new()->setContent(
                                            "Successfully created the note."
                                        ), true
                                    );
                                } else {
                                    global $logger;
                                    $logger->logError(
                                        $this->plan->planID,
                                        "An database error occurred while creating a note for the user: " . $interaction->user->id
                                    );
                                    $this->plan->utilities->acknowledgeMessage(
                                        $interaction,
                                        MessageBuilder::new()->setContent(
                                            "An database error occurred while creating the note."
                                        ), true
                                    );
                                }
                                break;
                            }
                        }
                    }
                },
            );
        }
    }

    public function edit(Interaction      $interaction,
                         int|float|string $key, string $title, ?string $description = null): void
    {
    }

    public function delete(Interaction      $interaction,
                           int|float|string $key, int|string|null $userID,
                           ?string          $deletionReason = null): void
    {
        $object = $this->get($interaction->guild_id, $key,
            $userID !== null ? $userID : $interaction->user->id);

        if ($object !== null) {

        } else {
            $this->plan->utilities->acknowledgeMessage(
                $interaction,
                MessageBuilder::new()->setContent(
                    "A note with this key does not exist or is not available to you."
                ), true
            );
        }
    }

    public function get(int|string $serverID, int|float|string $key, int|string|null $userID,
                                   $past = 1): ?object
    {
        $query = get_sql_query(
            BotDatabaseTable::BOT_NOTES,
            null,
            array(
                array("note_key", $key),
                array("user_id", $userID),
                array("server_id", $serverID),
                array("deletion_date", null)
            ),
            null,
            1
        );

        if (!empty($query)) {
            $query = $query[0];
            $childQuery = get_sql_query(
                BotDatabaseTable::BOT_NOTE_CHANGES,
                null,
                array(
                    array("note_id", $key),
                    array("deletion_date", null)
                ),
                array(
                    "DESC",
                    "id"
                ),
                $past
            );
            $size = sizeof($childQuery);

            if ($size === $past) {
                $query->changes = $childQuery[$size - 1];
                $query->settings = get_sql_query(
                    BotDatabaseTable::BOT_NOTE_SETTINGS,
                    null,
                    array(
                        array("note_id", $key),
                    ),
                    array(
                        "DESC",
                        "id"
                    ),
                    1
                )[0];
                return $query;
            }
        }
        return null;
    }

    public function send(Interaction      $interaction,
                         int|float|string $key, int|string|null $userID = null): void
    {
        $object = $this->get($interaction->guild_id, $key,
            $userID !== null ? $userID : $interaction->user->id);

        if ($object !== null) {
            //todo
        }
    }

    public function changeSetting(int|float|string $key,
                                  ?bool            $viewPublic = null,
                                  ?bool            $viewHistory = null)
    {

    }

    public function getParticipants(int|float|string $key, int|string|null $userID = null): array
    {
        return array();
    }

    public function modifyParticipant(int|float|string $key, int|string $userID, int|string $participantID,
                                      ?bool            $readHistory = null,
                                      ?bool            $writePermission = null,
                                      ?bool            $deletePermission = null): ?string
    {
        return null;
    }

    public function removeParticipant(int|float|string $key,): ?string
    {
        return null;
    }
}