<?php

use Discord\Parts\Channel\Message;

class DiscordChatFilteredMessages
{
    private DiscordPlan $plan;
    private array $filters;

    private const AI_HASH = 248901024;

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
                $filter->localInstructions = get_sql_query(
                    BotDatabaseTable::BOT_MESSAGE_MESSAGE_FILTER_INSTRUCTIONS,
                    null,
                    array(
                        array("deletion_date", null),
                        array("filter_id", $filter->id),
                        array("public", null),
                        null,
                        array("expiration_date", "IS", null, 0),
                        array("expiration_date", ">", get_current_date()),
                        null
                    )
                );
                if (!empty($filter->localInstructions)) {
                    foreach ($filter->localInstructions as $childKey => $instruction) {
                        $filter->localInstructions[$childKey] = $instruction->instruction_id;
                    }
                }
                $filter->publicInstructions = get_sql_query(
                    BotDatabaseTable::BOT_MESSAGE_MESSAGE_FILTER_INSTRUCTIONS,
                    null,
                    array(
                        array("deletion_date", null),
                        array("filter_id", $filter->id),
                        array("public", 1),
                        null,
                        array("expiration_date", "IS", null, 0),
                        array("expiration_date", ">", get_current_date()),
                        null
                    )
                );
                if (!empty($filter->publicInstructions)) {
                    foreach ($filter->publicInstructions as $childKey => $instruction) {
                        $filter->publicInstructions[$childKey] = $instruction->instruction_id;
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
                    $object = $this->plan->instructions->getObject(
                        $message->guild,
                        $message->channel,
                        $message->member,
                        $message
                    );

                    if (!empty($filter->blockedWords)) {
                        foreach ($filter->blockedWords as $blockedWord) {
                            foreach ($this->getCombinations($filter, $blockedWord->word) as $word) {
                                if (str_contains($message->content, $word)
                                    && $this->canBlock($message, $blockedWord)) {
                                    return $this->plan->instructions->replace(
                                        array($blockedWord->message ?? ""),
                                        $object
                                    )[0];
                                }
                            }
                        }
                    }
                    if (!empty($filter->localInstructions)) {
                        $reply = $this->plan->aiMessages->rawTextAssistance(
                            $message,
                            null,
                            $this->plan->instructions->build(
                                $object,
                                $filter->localInstructions,
                                $filter->publicInstructions,
                            ),
                            self::AI_HASH
                        );
                    }
                }
            }
        }
        return null;
    }

    private function canBlock(Message $message, object $row, int|string $constantID = null): bool
    {
        if ($row->points === null
            || $row->seconds_period === null) {
            $block = true;
        } else {
            $key = array(
                __METHOD__,
                $row->id,
                $message->user_id
            );
            $block = has_memory_limit($key, $row->points, $row->seconds_period);
        }

        if ($block) {
            $channel = $this->plan->utilities->getChannel($message->channel);

            if (!sql_insert(
                BotDatabaseTable::BOT_MESSAGE_MESSAGE_FILTER_TRACKING,
                array(
                    array("filter_id", $row->id),
                    array("constant_id", $constantID),
                    array("server_id", $channel->guild_id),
                    array("category_id", $channel->parent_id),
                    array("channel_id", $channel->id),
                    array("thread_id", $message->thread?->id),
                    array("user_id", $message->user_id),
                    array("message_id", $message->id),
                    array("message_content", $message->content),
                    array("message", $row->message),
                    array("mute_period", $row->mute_period),
                    array("creation_date", get_current_date())
                )
            )) {
                global $logger;
                $logger->logError(
                    $this->plan,
                    "Failed to insert message-filter tracking with analyzed"
                    . ($constantID !== null ? " constant " : " ") . "ID: " . $row->id
                );
            }
        }
        return $block;
    }

    private function getCombinations(object $filter, string $word): array
    {
        if (!empty($filter->letterCorrelations)) {
            $array = array($word);
            //todo
            return $array;
        } else {
            return array($word);
        }
    }
}