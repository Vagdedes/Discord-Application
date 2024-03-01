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

                if (!empty($filter->letterCorrelations)) {
                    foreach ($filter->letterCorrelations as $childKey => $correlation) {
                        unset($filter->letterCorrelations[$childKey]);

                        if (array_key_exists($correlation->letter, $filter->letterCorrelations)) {
                            $filter->letterCorrelations[$correlation->letter][] = $correlation->letter_correlation;
                        } else {
                            $filter->letterCorrelations[$correlation->letter] = array($correlation->letter_correlation);
                        }
                    }
                }
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
                        array("public", "IS NOT", null),
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
                                if (str_contains($message->content, $word)) {
                                    $blockMessage = $this->getBlockMessage(
                                        $message,
                                        $object,
                                        $blockedWord
                                    );

                                    if ($blockMessage !== null) {
                                        return $blockMessage;
                                    }
                                }
                            }
                        }
                    }
                    if ($filter->ai_model_id !== null
                        && !empty($filter->constants)) {
                        $reply = $this->plan->aiMessages->rawTextAssistance(
                            $filter->ai_model_id,
                            $message,
                            null,
                            array(
                                $object,
                                empty($filter->localInstructions) ? null : $filter->localInstructions,
                                empty($filter->publicInstructions) ? null : $filter->publicInstructions
                            ),
                            self::AI_HASH
                        );

                        if ($reply !== null) {
                            foreach ($filter->constants as $constant) {
                                if ($reply == $constant->constant_key) {
                                    $blockMessage = $this->getBlockMessage(
                                        $message,
                                        $object,
                                        $constant,
                                        $constant->id
                                    );

                                    if ($blockMessage !== null) {
                                        return $blockMessage;
                                    }
                                    break;
                                }
                            }
                        }
                    }
                }
            }
        }
        return null;
    }

    private function getBlockMessage(Message    $message,
                                     object     $object,
                                     object     $row,
                                     int|string $constantID = null): ?string
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
                    "filter_id" => $row->id,
                    "constant_id" => $constantID,
                    "server_id" => $channel->guild_id,
                    "category_id" => $channel->parent_id,
                    "channel_id" => $channel->id,
                    "thread_id" => $message->thread?->id,
                    "user_id" => $message->user_id,
                    "message_id" => $message->id,
                    "message_content" => $message->content,
                    "message" => $row->message,
                    "mute_period" => $row->mute_period,
                    "creation_date" => get_current_date()
                )
            )) {
                global $logger;
                $logger->logError(
                    $this->plan,
                    "Failed to insert message-filter tracking with analyzed"
                    . ($constantID !== null ? " constant " : " ") . "ID: " . $row->id
                );
            }
            $blockMessage = $this->plan->instructions->replace(
                array($row->message ?? ""),
                $object
            )[0];

            if (!empty($blockMessage) && $row->mute_period !== null) {
                $this->plan->bot->mute->mute(
                    $this->plan->bot->discord->user,
                    $message->member,
                    $message->channel,
                    $blockMessage,
                    DiscordMute::TEXT,
                    $row->mute_period
                );
            }
            return $blockMessage;
        }
        return null;
    }

    private function getCombinations(object $filter, string $word): array
    {
        if (!empty($filter->letterCorrelations)) {
            $array = array();

            foreach ($filter->letterCorrelations as $letter => $correlations) {
                $correlations[] = $letter;
                $occurrences = find_character_occurrences($word, $letter);
                $correlationCount = sizeof($correlations);

                for ($i = 0; $i < pow($correlationCount, sizeof($occurrences)); $i++) {
                    $wordCopy = $word;
                    $count = $i;

                    foreach ($occurrences as $occurrence) {
                        $wordCopy[$occurrence] = $correlations[$count % $correlationCount];
                        $count /= $correlationCount;
                    }
                    $array[] = $wordCopy;
                }
            }
            return $array;
        } else {
            return array($word);
        }
    }
}