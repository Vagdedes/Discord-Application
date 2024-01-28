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
                $filter->constants = array();
                $constants = get_sql_query(
                    BotDatabaseTable::BOT_MESSAGE_MESSAGE_FILTER_CONSTANTS,
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
                if (!empty($constants)) {
                    foreach ($constants as $constant) {
                        $filter->constants[$constant->id] = $constant;
                    }
                }
                $this->filters[$arrayKey] = $filter;
            }
        }
    }

    public function run(Message $message): ?string
    {
        if (!empty($this->filters)) {
            foreach ($this->filters as $filter) {
                if (!empty($filter->keywords)) {
                    $check = false;

                    foreach ($filter->keywords as $keyword) {
                        if (str_contains($message->content, $keyword->keyword)) {
                            $check = true;
                            break;
                        }
                    }
                } else {
                    $check = true;
                }

                if ($check) {
                    if (!empty($filter->blockedWords)) {
                        foreach ($filter->blockedWords as $blockedWord) {
                            foreach ($this->getCombinations($filter, $blockedWord->word) as $word) {
                                if (str_contains($message->content, $word)
                                    && $this->canBlock($message, $blockedWord, true)) {
                                    return $blockedWord->message;
                                }
                            }
                        }
                    }
                }
            }
        }
        return null;
    }

    private function canBlock(Message $message, object $row, bool $word): bool
    {
        if ($row->points === null
            || $row->seconds_period === null) {
            $block = true;
        } else {
            $key = array(
                ""
            );
            $block = has_memory_limit($key, $row->points, $row->seconds_period);
        }
        return $block;
    }

    private function getCombinations(object $filter, string $word): array
    {
        return array($word);
    }
}