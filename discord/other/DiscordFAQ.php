<?php

use Discord\Builders\Components\Option;
use Discord\Builders\Components\SelectMenu;
use Discord\Builders\MessageBuilder;
use Discord\Helpers\Collection;
use Discord\Parts\Embed\Embed;
use Discord\Parts\Interactions\Interaction;

class DiscordFAQ
{
    private DiscordBot $bot;

    public function __construct(DiscordBot $bot)
    {
        $this->bot = $bot;
    }

    public function addOrEdit(Interaction $interaction,
                              string      $question, string $answer): ?string
    {
        $question = trim($question);
        $answer = trim($answer);
        $query = get_sql_query(
            BotDatabaseTable::BOT_FAQ,
            null,
            array(
                array("question", $question),
                array("server_id", $interaction->guild_id),
                array("deletion_date", null)
            ),
            array(
                "DESC",
                "id"
            ),
            1
        );

        if (empty($query)) {
            if (sql_insert(
                BotDatabaseTable::BOT_FAQ,
                array(
                    "server_id" => $interaction->guild_id,
                    "question" => $question,
                    "answer" => $answer,
                    "creation_date" => get_current_date(),
                    "created_by" => $interaction->member->id
                )
            )) {
                return null;
            } else {
                return "Failed to add the frequently-asked question to the database.";
            }
        } else if (set_sql_query(
            BotDatabaseTable::BOT_FAQ,
            array(
                "answer" => $answer
            ),
            array(
                array("id", $query[0]->id)
            ),
            null,
            1
        )) {
            return null;
        } else {
            return "Failed to update the frequently-asked question in the database.";
        }
    }

    public function delete(Interaction $interaction,
                           string      $question): ?string
    {
        $question = trim($question);

        if (set_sql_query(
            BotDatabaseTable::BOT_FAQ,
            array(
                "deletion_date" => get_current_date(),
                "deleted_by" => $interaction->member->id
            ),
            array(
                array("question", $question),
                array("server_id", $interaction->guild_id),
                array("deletion_date", null)
            )
        )) {
            return null;
        } else {
            return "Failed to delete the frequently-asked question from the database.";
        }
    }

    public function list(Interaction $interaction): MessageBuilder
    {
        $builder = new MessageBuilder();
        $query = get_sql_query(
            BotDatabaseTable::BOT_FAQ,
            null,
            array(
                array("server_id", $interaction->guild_id),
                array("deletion_date", null)
            ),
            array(
                "DESC",
                "id"
            ),
            DiscordInheritedLimits::MAX_CHOICES_PER_SELECTION
        );

        if (empty($query)) {
            $builder->setContent("There are no frequently-asked questions set-up in this server.");
        } else {
            $selection = SelectMenu::new()
                ->setPlaceholder("Select a question to view the answer.")
                ->setMinValues(1)
                ->setMaxValues(1);

            foreach ($query as $index => $row) {
                $selection->addOption(
                    Option::new($row->question, $index)->setDescription(
                        substr($row->answer, 0, 100)
                    )
                );
            }
            $selection->setListener(function (Interaction $interaction, Collection $options) use ($query) {
                $index = $options[0]->getValue();
                $embed = new Embed($this->bot->discord);
                $embed->setTitle($query[$index]->question);
                $embed->setDescription($query[$index]->answer);

                $this->bot->utilities->acknowledgeMessage(
                    $interaction,
                    MessageBuilder::new()->addEmbed($embed),
                    true
                );
            }, $this->bot->discord);
            $builder->addComponent($selection);
        }
        return $builder;
    }
}