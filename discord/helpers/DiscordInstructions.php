<?php

use Discord\Parts\Channel\Channel;
use Discord\Parts\Channel\Message;
use Discord\Parts\Guild\Guild;
use Discord\Parts\Thread\Thread;
use Discord\Parts\User\Member;
use Discord\Parts\User\User;

class DiscordInstructions
{

    private DiscordPlan $plan;
    private array $localInstructions, $publicInstructions, $placeholders;

    public function __construct(DiscordPlan $plan)
    {
        $this->plan = $plan;
        $this->localInstructions = get_sql_query(
            BotDatabaseTable::BOT_LOCAL_INSTRUCTIONS,
            null,
            array(
                array("deletion_date", null),
                array("application_id", $this->plan->applicationID),
                null,
                array("plan_id", "IS", null, 0),
                array("plan_id", "=", $this->plan->planID, 0),
                $this->plan->family !== null ? array("family", $this->plan->family) : "",
                null,
                null,
                array("expiration_date", "IS", null, 0),
                array("expiration_date", ">", get_current_date()),
                null
            ),
            "plan_id ASC, priority DESC"
        );
        $this->publicInstructions = get_sql_query(
            BotDatabaseTable::BOT_PUBLIC_INSTRUCTIONS,
            null,
            array(
                array("deletion_date", null),
                array("application_id", $this->plan->applicationID),
                null,
                array("plan_id", "IS", null, 0),
                array("plan_id", "=", $this->plan->planID, 0),
                $this->plan->family !== null ? array("family", $this->plan->family) : "",
                null,
                null,
                array("expiration_date", "IS", null, 0),
                array("expiration_date", ">", get_current_date()),
                null
            ),
            "plan_id ASC, priority DESC"
        );
        $this->placeholders = get_sql_query(
            BotDatabaseTable::BOT_INSTRUCTION_PLACEHOLDERS,
            null,
            array(
                array("deletion_date", null),
                null,
                array("plan_id", "IS", null, 0),
                array("plan_id", $this->plan->planID),
                null,
                null,
                array("expiration_date", "IS", null, 0),
                array("expiration_date", ">", get_current_date()),
                null
            )
        );
    }

    public function replace(array  $messages, ?object $object,
                            string $placeholderStart = DiscordProperties::DEFAULT_PLACEHOLDER_START,
                            string $placeholderMiddle = DiscordProperties::DEFAULT_PLACEHOLDER_MIDDLE,
                            string $placeholderEnd = DiscordProperties::DEFAULT_PLACEHOLDER_END,
                            bool   $recursive = true): array
    {
        if (!empty($this->placeholders)) {
            $hasObject = $object !== null;
            $replaceFurther = false;

            foreach ($messages as $arrayKey => $message) {
                if ($message === null) {
                    $messages[$arrayKey] = "";
                }
            }
            foreach ($this->placeholders as $placeholder) {
                if ($hasObject && isset($object->{$placeholder->placeholder})) {
                    $value = $object->{$placeholder->code_field};
                } else if ($placeholder->dynamic !== null) {
                    $keyWord = explode($placeholderMiddle, $placeholder->placeholder, 3);
                    $limit = sizeof($keyWord) === 2 ? $keyWord[1] : 0;

                    switch ($keyWord[0]) {
                        case "publicInstructions":
                            $value = $this->getPublic();
                            $replaceFurther = $recursive;
                            break;
                        case "botReplies":
                            $value = $hasObject
                                ? $this->plan->conversation->getReplies($object->userID, $limit, false)
                                : "";
                            break;
                        case "botMessages":
                            $value = $hasObject
                                ? $this->plan->conversation->getMessages($object->userID, $limit, false)
                                : "";
                            break;
                        case "allMessages":
                            $value = $hasObject
                                ? $this->plan->conversation->getConversation($object->userID, $limit, false)
                                : "";
                            break;
                        default:
                            $value = "";
                            break;
                    }
                } else {
                    $value = "";
                }

                if (is_array($value)) {
                    $array = $value;
                    $size = sizeof($array);
                    $value = "";

                    foreach ($array as $arrayKey => $row) {
                        if ($replaceFurther) {
                            $value .= $this->replace(
                                array($row),
                                $object,
                                $placeholderStart,
                                $placeholderMiddle,
                                $placeholderEnd,
                                false
                            )[0];
                        } else {
                            $value .= $row;
                        }

                        if ($arrayKey !== ($size - 1)) {
                            $value .= DiscordProperties::NEW_LINE;
                        }
                    }
                }
                $object->placeholderArray[] = $value;

                if ($placeholder->include_previous !== null) {
                    $size = sizeof($object->placeholderArray);

                    for ($position = 1; $position <= min($placeholder->include_previous, $size); $position++) {
                        $positionValue = $object->placeholderArray[$size - $position];

                        foreach ($messages as $arrayKey => $message) {
                            if (!empty($message)) {
                                $messages[$arrayKey] = str_replace(
                                    $placeholderStart . $placeholder->placeholder . $placeholderEnd,
                                    $positionValue,
                                    $message
                                );
                            }
                        }
                    }
                }
                foreach ($messages as $arrayKey => $message) {
                    if (!empty($message)) {
                        $messages[$arrayKey] = str_replace(
                            $placeholderStart . $placeholder->placeholder . $placeholderEnd,
                            $value,
                            $message
                        );
                    }
                }
            }
        }
        return $messages;
    }

    public function build(object $object, ?array $specific = null): array
    {
        if (!empty($this->localInstructions)) {
            $information = "";
            $disclaimer = "";
            $hasSpecific = $specific !== null;

            foreach ($this->localInstructions as $instruction) {
                if ($hasSpecific
                    ? in_array($instruction->id, $specific)
                    : $instruction->use !== null) {
                    $replacements = $this->replace(
                        array(
                            $instruction->information,
                            $instruction->disclaimer
                        ),
                        $object,
                        $instruction->placeholder_start,
                        $instruction->placeholder_middle,
                        $instruction->placeholder_end
                    );
                    $information .= $replacements[0];
                    $disclaimer .= $replacements[1];
                }
            }
            if ($object->channel !== null
                && $object->channel->strict_reply !== null) {
                $information = ($object->channel->require_mention
                        ? DiscordProperties::STRICT_REPLY_INSTRUCTIONS_WITH_MENTION
                        : DiscordProperties::STRICT_REPLY_INSTRUCTIONS)
                    . DiscordProperties::NEW_LINE . DiscordProperties::NEW_LINE . $information;
            }
            if (!empty($information)) {
                return array(
                    $information,
                    (!empty($disclaimer)
                        ? DiscordProperties::NEW_LINE . DiscordSyntax::SPOILER . $disclaimer . DiscordSyntax::SPOILER
                        : "")
                );
            }
        }
        return array("", "");
    }

    public function getObject(?Guild              $server = null,
                              Channel|Thread|null $channel = null,
                              ?Thread             $thread = null,
                              Member|User|null    $user = null,
                              ?Message            $message = null): object
    {
        $object = new stdClass();
        $object->serverID = $server?->id;
        $object->serverName = $server?->name;
        $object->channelID = $channel instanceof Thread
            ? $channel->parent?->id
            : $channel?->id;
        $object->channelName = $channel instanceof Thread
            ? $channel->parent?->name
            : $channel?->name;
        $object->threadID = $thread?->id;
        $object->threadName = $thread?->name;
        $object->userID = $user?->id;
        $object->userName = $user?->username;
        $object->displayName = $user?->displayname;
        $object->messageContent = $message?->content;
        $object->messageID = $message?->id;
        $object->botID = $this->plan->botID;
        $object->botName = $this->plan->discord->user->id;
        $object->domain = get_domain();
        $object->date = get_current_date();
        $object->year = date("Y");
        $object->month = date("m");
        $object->hour = date("H");
        $object->minute = date("i");
        $object->second = date("s");
        $object->channel = $object->serverID === null || $object->channelID === null || $object->userID === null ? null
            : $this->plan->locations->getChannel($object->serverID, $object->channelID, $object->userID);

        $object->placeholderArray = array();
        $object->newLine = DiscordProperties::NEW_LINE;

        $object->planName = $this->plan->name;
        $object->planDescription = $this->plan->description;
        $object->planCreationDate = $this->plan->creationDate;
        $object->planCreationReason = $this->plan->creationReason;
        $object->planExpirationDate = $this->plan->expirationDate;
        $object->planExpirationReason = $this->plan->expirationReason;
        return $object;
    }

    private function getPublic(): array
    {
        $cacheKey = array(__METHOD__, $this->plan->applicationID, $this->plan->planID);
        $cache = get_key_value_pair($cacheKey);

        if ($cache !== null) {
            return $cache;
        } else {
            $times = array();
            $array = $this->publicInstructions;

            if (!empty($array)) {
                global $logger;

                foreach ($array as $arrayKey => $row) {
                    $timeKey = strtotime(get_future_date($row->information_duration));

                    if ($row->information_expiration !== null
                        && $row->information_expiration > get_current_date()) {
                        $times[$timeKey] = $row->information_duration;
                        $array[$arrayKey] = $row->information_value;
                    } else {
                        $doc = get_domain_from_url($row->information_url) == "docs.google.com"
                            ? get_raw_google_doc($row->information_url) :
                            timed_file_get_contents($row->information_url);

                        if ($doc !== null) {
                            $times[$timeKey] = $row->information_duration;
                            $array[$arrayKey] = $doc;
                            set_sql_query(
                                BotDatabaseTable::BOT_PUBLIC_INSTRUCTIONS,
                                array(
                                    "information_value" => $doc,
                                    "information_expiration" => get_future_date($row->information_duration)
                                ),
                                array(
                                    array("id", $row->id)
                                ),
                                null,
                                1
                            );
                        } else {
                            $logger->logError($this->plan->planID, "Failed to retrieve value for: " . $row->information_url);

                            if ($row->information_value !== null) {
                                $times[$timeKey] = $row->information_duration;
                                $array[$arrayKey] = $row->information_value;
                                $logger->logError($this->plan->planID, "Used backup value for: " . $row->information_url);
                            } else {
                                unset($array[$arrayKey]);
                            }
                        }
                    }
                }

                if (!empty($times)) {
                    ksort($times);
                    set_key_value_pair($cacheKey, $array, array_shift($times));
                } else {
                    set_key_value_pair($cacheKey, $array, "1 minute");
                }
            }
            return $array;
        }
    }
}