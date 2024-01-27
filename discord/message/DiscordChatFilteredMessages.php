<?php

use Discord\Parts\Channel\Message;

class DiscordChatFilteredMessages
{
    private DiscordPlan $plan;
    private array $filters;

    public function __construct(DiscordPlan $plan)
    {
        $this->plan = $plan;
        $this->filters = get_sql_query(
            BotDatabaseTable::BOT_MESSAGE_MESSAGE_FILTER,
            null,
            array(
                array("deletion_date", null),
                array("plan_id", $this->plan->planID),
                null,
                array("expiration_date", "IS", null, 0),
                array("expiration_date", ">", get_current_date()),
                null
            )
        );

        if (!empty($this->filters)) {
            foreach ($this->filters as $arrayKey => $filter) {
                $filter->keywords = get_sql_query(
                    BotDatabaseTable::BOT_MESSAGE_MESSAGE_FILTER_KEYWORDS,
                    null,
                    array(
                        array("deletion_date", null),
                        array("filter_id", $filter->id),
                        null,
                        array("expiration_date", "IS", null, 0),
                        array("expiration_date", ">", get_current_date()),
                        null
                    )
                );
                $filter->blockedWords = get_sql_query(
                    BotDatabaseTable::BOT_MESSAGE_MESSAGE_FILTER_BLOCKED_WORDS,
                    null,
                    array(
                        array("deletion_date", null),
                        array("filter_id", $filter->id),
                        null,
                        array("expiration_date", "IS", null, 0),
                        array("expiration_date", ">", get_current_date()),
                        null
                    )
                );
                $filter->letterCorrelations = get_sql_query(
                    BotDatabaseTable::BOT_MESSAGE_MESSAGE_FILTER_LETTER_CORRELATIONS,
                    null,
                    array(
                        array("deletion_date", null),
                        array("filter_id", $filter->id),
                        null,
                        array("expiration_date", "IS", null, 0),
                        array("expiration_date", ">", get_current_date()),
                        null
                    )
                );
                $filter->instructions = get_sql_query(
                    BotDatabaseTable::BOT_MESSAGE_MESSAGE_FILTER_INSTRUCTIONS,
                    null,
                    array(
                        array("deletion_date", null),
                        array("filter_id", $filter->id),
                        null,
                        array("expiration_date", "IS", null, 0),
                        array("expiration_date", ">", get_current_date()),
                        null
                    )
                );

                if (!empty($filter->instructions)) {
                    foreach ($filter->instructions as $childKey => $instruction) {
                        unset($filter->instructions[$childKey]);
                        $filter->instructions[] = $instruction->instruction_id;
                    }
                }
                $this->filters[$arrayKey] = $filter;
            }
        }
    }

    public function run(Message $message): ?string
    {
        if (!empty($this->filters)) {

        }
        return null;
    }
}