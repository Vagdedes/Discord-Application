<?php

use Discord\Builders\MessageBuilder;
use Discord\Parts\Channel\Message;
use Discord\Parts\Embed\Embed;

class DiscordCounting
{
    private DiscordPlan $plan;
    private array $countingPlaces;
    public int $ignoreDeletion;

    //todo counting-goal commands to list goals of users

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
        if ($message->author->id != $this->plan->botID) {
            $rowArray = $this->getCountingChannelObject($message);

            if ($rowArray !== null) {
                $row = $rowArray[1];

                if (strlen($message->content) <= 20) {
                    if (is_numeric($message->content)) {
                        if ($row->allow_decimals !== null || is_int($message->content)) {
                            if ($message->content > $row->current_number) {
                                if ($message->content <= $row->max_number) {
                                    $pattern = $row->number_pattern !== null ? $row->number_pattern : 1;

                                    if ($message->content == ($row->current_number + $pattern)) {
                                        $maxRepetitions = $row->max_repetitions !== null ? $row->max_repetitions : 1;
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
                                            $maxRepetitions
                                        );
                                        $querySize = sizeof($query);

                                        if ($querySize == $maxRepetitions) {
                                            $count = 0;

                                            foreach ($query as $row) {
                                                if ($row->user_id == $message->author->id) {
                                                    $count++;
                                                }
                                            }

                                            if ($count >= $maxRepetitions) {
                                                $this->sendNotification($row, $message, "Too Many Repetitions");
                                                return true;
                                            }
                                        }
                                        if (sql_insert(
                                            BotDatabaseTable::BOT_COUNTING_MESSAGES,
                                            array(
                                                "counting_id" => $row->id,
                                                "user_id" => $message->author->id,
                                                "message_id" => $message->id,
                                                "sent_number" => $message->content,
                                                "creation_date" => get_current_date()
                                            )
                                        )) {
                                            if (set_sql_query(
                                                BotDatabaseTable::BOT_COUNTING,
                                                array(
                                                    "current_number" => $message->content
                                                ),
                                                array(
                                                    array("id", $row->id),
                                                ),
                                                null,
                                                1
                                            )) {
                                                $row->current_number = $message->content;
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
        if ($message instanceof Message && $this->getCountingChannelObject($message) !== null) {
            $message->channel->sendMessage("<@{$message->author->id}> " . $message->content);
            return true;
        }
        return false;
    }

    private function triggerGoal(Message $message, object $row): void
    {
        if (!empty($row->goals)) {
            foreach ($row->goals as $goal) {
                if ($goal->target_number == $message->content) {
                    if (sql_insert(
                        BotDatabaseTable::BOT_COUNTING_GOAL_STORAGE,
                        array(
                            "counting_id" => $row->id,
                            "goal_id" => $goal->id,
                            "server_id" => $message->guild_id,
                            "user_id" => $message->author->id,
                            "creation_date" => get_current_date()
                        )
                    )) {
                        if ($goal->user_message !== null) {
                            $message->reply($this->plan->utilities->buildMessageFromObject($goal));
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

    private function getCountingChannelObject(Message $message): ?array
    {
        if (!empty($this->countingPlaces)) {
            foreach ($this->countingPlaces as $key => $row) {
                if ($row->server_id == $message->guild_id
                    && ($row->channel_id == $message->channel_id
                        || $row->channel_id === null)
                    && ($row->thread_id == $message->thread?->id
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
            $channel = $this->plan->discord->getChannel($row->notifications_channel_id);

            if ($channel !== null && $channel->guild_id == $row->server_id) {
                $messageBuilder = MessageBuilder::new();
                $embed = new Embed($this->plan->discord);
                $embed->setAuthor($message->author->username, $message->author->avatar);
                $embed->setTitle($title);
                $embed->setDescription($message->content);
                $messageBuilder->addEmbed($embed);
                $channel->sendMessage($messageBuilder);
            }
        }
        $message->delete();
    }
}