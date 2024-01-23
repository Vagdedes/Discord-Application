<?php

use Discord\Builders\MessageBuilder;
use Discord\Parts\Channel\Message;
use Discord\Parts\Embed\Embed;

class DiscordCountingChannels
{
    private DiscordPlan $plan;
    private array $countingPlaces;
    public int $ignoreDeletion;

    private const REFRESH_TIME = "15 seconds";

    public function __construct(DiscordPlan $plan)
    {
        $this->plan = $plan;
        $this->ignoreDeletion = 0;
        $this->countingPlaces = get_sql_query(
            BotDatabaseTable::BOT_COUNTING,
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

        if (!empty($this->countingPlaces)) {
            foreach ($this->countingPlaces as $row) {
                $row->goals = get_sql_query(
                    BotDatabaseTable::BOT_COUNTING_GOALS,
                    null,
                    array(
                        array("deletion_date", null),
                        array("counting_id", $row->id),
                        null,
                        array("expiration_date", "IS", null, 0),
                        array("expiration_date", ">", get_current_date()),
                        null
                    )
                );
            }
        }
    }

    public function track(Message $message): bool
    {
        if ($message->author->id != $this->plan->bot->botID) {
            $rowArray = $this->getCountingChannelObject($message);

            if ($rowArray !== null) {
                $row = $rowArray[1];

                if (strlen($message->content) <= 20) {
                    if (is_numeric($message->content)) {
                        if ($row->allow_decimals !== null || !is_float($message->content)) {
                            if ($message->content > $row->current_number) {
                                if ($message->content <= $row->max_number) {
                                    $pattern = $row->number_pattern !== null ? $row->number_pattern : 1;
                                    $sent = trim($message->content);

                                    if ($sent == ($row->current_number + $pattern)) {
                                        if ($row->max_repetitions !== null) {
                                            $query = get_sql_query(
                                                BotDatabaseTable::BOT_COUNTING_MESSAGES,
                                                array("user_id"),
                                                array(
                                                    array("counting_id", $row->id),
                                                    array("deletion_date", null),
                                                ),
                                                array(
                                                    "DESC",
                                                    "id"
                                                ),
                                                $row->max_repetitions
                                            );
                                            $querySize = sizeof($query);

                                            if ($querySize == $row->max_repetitions) {
                                                $count = 0;

                                                foreach ($query as $single) {
                                                    if ($single->user_id == $message->author->id) {
                                                        $count++;
                                                    }
                                                }

                                                if ($count >= $row->max_repetitions) {
                                                    $this->sendNotification($row, $message, "Too Many Repetitions");
                                                    return true;
                                                }
                                            }
                                        }
                                        if (sql_insert(
                                            BotDatabaseTable::BOT_COUNTING_MESSAGES,
                                            array(
                                                "counting_id" => $row->id,
                                                "user_id" => $message->author->id,
                                                "message_id" => $message->id,
                                                "sent_number" => $sent,
                                                "creation_date" => get_current_date()
                                            )
                                        )) {
                                            if (set_sql_query(
                                                BotDatabaseTable::BOT_COUNTING,
                                                array(
                                                    "current_number" => $sent
                                                ),
                                                array(
                                                    array("id", $row->id),
                                                ),
                                                null,
                                                1
                                            )) {
                                                $row->current_number = $sent;
                                                $this->countingPlaces[$rowArray[0]] = $row;
                                                $this->triggerGoal($message, $row);
                                            } else {
                                                $this->sendNotification($row, $message, "Database Error (2)");
                                            }
                                        } else {
                                            $this->sendNotification($row, $message, "Database Error (1)");
                                        }
                                    } else {
                                        $this->sendNotification($row, $message, "Wrong Number Pattern");
                                    }
                                } else {
                                    $this->sendNotification($row, $message, "Bigger Number");
                                }
                            } else {
                                $this->sendNotification($row, $message, "Smaller or Equal Number");
                            }
                        } else {
                            $this->sendNotification($row, $message, "Decimal Number");
                        }
                    } else {
                        $this->sendNotification($row, $message, "Not a Number");
                    }
                } else {
                    $this->sendNotification($row, $message, "Too Long Number");
                }
                return true;
            }
        }
        return false;
    }

    public function restore(object $message): bool
    {
        if ($this->getCountingChannelObject($message) !== null) {
            $message->channel?->sendMessage(
                (isset($message->author) ? "<@{$message->author->id}> " : "<" . $message->id . "> ")
                . trim(strip_tags($message->content))
            );
            return true;
        }
        return false;
    }

    public function moderate(Message $message): bool
    {
        $rowArray = $this->getCountingChannelObject($message);

        if ($rowArray !== null) {
            $this->ignoreDeletion++;
            $message->delete();
            $message->channel->sendMessage("<@{$message->author->id}> " . $rowArray[1]->current_number);
            return true;
        }
        return false;
    }

    public function getStoredGoals(int|string $userID, int $limit = 0): array
    {
        if (!empty($this->countingPlaces)) {
            $array = array();
            $hasLimit = $limit > 0;

            foreach ($this->countingPlaces as $row) {
                if (!empty($row->goals)) {
                    foreach ($row->goals as $goal) {
                        set_sql_cache(self::REFRESH_TIME);
                        $storage = get_sql_query(
                            BotDatabaseTable::BOT_COUNTING_GOAL_STORAGE,
                            null,
                            array(
                                array("goal_id", $goal->id),
                                array("user_id", $userID),
                                array("deletion_date", null),
                            ),
                            array(
                                "DESC",
                                "id"
                            ),
                            1
                        );

                        if (!empty($storage)) {
                            $array[] = $goal;

                            if ($hasLimit && sizeof($array) == $limit) {
                                return $array;
                            }
                        }
                    }
                }
            }
            return $array;
        }
        return array();
    }

    public function loadStoredGoalMessages(int|string $userID, array $goals): MessageBuilder
    {
        $messageBuilder = MessageBuilder::new();
        $messageBuilder->setContent("Showing last **" . sizeof($goals) . " counting goals** of user **" . $this->plan->utilities->getUsername($userID) . "**");

        foreach ($goals as $goal) {
            $embed = new Embed($this->plan->bot->discord);
            $embed->setTitle($goal->title);

            if ($goal->description !== null) {
                $embed->setDescription($goal->description);
            }
            $embed->setTimestamp(strtotime($goal->creation_date));
            $messageBuilder->addEmbed($embed);
        }
        return $messageBuilder;
    }

    private function triggerGoal(Message $message, object $row): void
    {
        if (!empty($row->goals)) {
            foreach ($row->goals as $goal) {
                if ($goal->target_number == $message->content) {
                    if (sql_insert(
                        BotDatabaseTable::BOT_COUNTING_GOAL_STORAGE,
                        array(
                            "goal_id" => $goal->id,
                            "server_id" => $message->guild_id,
                            "user_id" => $message->author->id,
                            "creation_date" => get_current_date()
                        )
                    )) {
                        $messageBuilder = $this->plan->utilities->buildMessageFromObject(
                            $goal,
                            $this->plan->instructions->getObject(
                                $message->guild,
                                $message->channel,
                                $message->thread,
                                $message->member,
                                $message
                            )
                        );

                        if ($messageBuilder !== null) {
                            $message->reply($messageBuilder);
                        } else {
                            $this->plan->listener->callCountingGoalImplementation(
                                $goal->listener_class,
                                $goal->listener_method,
                                $message,
                                $row
                            );
                        }
                    }
                    break;
                }
            }
        }
    }

    private function getCountingChannelObject(object $message): ?array
    {
        if (!empty($this->countingPlaces)) {
            $hasThread = isset($message->thread);

            foreach ($this->countingPlaces as $key => $row) {
                if ($row->server_id == $message->guild_id
                    && ($row->channel_id == $message->channel_id
                        || $row->channel_id === null)
                    && (!$hasThread || $row->thread_id == $message->thread?->id
                        || $row->thread_id === null)) {
                    return array($key, $row);
                }
            }
        }
        return null;
    }

    private function sendNotification(object  $row,
                                      Message $message,
                                      string  $title): void
    {
        if ($row->notifications_channel_id !== null) {
            $channel = $this->plan->bot->discord->getChannel($row->notifications_channel_id);

            if ($channel !== null
                && $channel->allowText()
                && $channel->guild_id == $row->server_id) {
                $messageBuilder = MessageBuilder::new();
                $embed = new Embed($this->plan->bot->discord);
                $embed->setAuthor($message->author->username, $message->author->avatar);
                $embed->setTitle($title);
                $embed->setDescription($message->content);
                $messageBuilder->addEmbed($embed);
                $channel->sendMessage($messageBuilder);
            }
        }
        $this->ignoreDeletion++;
        $message->delete();
    }
}