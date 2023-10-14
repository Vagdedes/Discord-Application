<?php

class DiscordInstructions
{

    private DiscordPlan $plan;
    private array $instructions, $placeholders;

    public function __construct(DiscordPlan $plan)
    {
        $this->plan = $plan;
        $this->instructions = get_sql_query(
            BotDatabaseTable::BOT_INSTRUCTIONS,
            null,
            array(
                array("deletion_date", null),
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

    public function replace(array  $messages, ?object $object = null,
                            string $placeholderStart = DiscordProperties::DEFAULT_PLACEHOLDER_START,
                            string $placeholderMiddle = DiscordProperties::DEFAULT_PLACEHOLDER_MIDDLE,
                            string $placeholderEnd = DiscordProperties::DEFAULT_PLACEHOLDER_END): array
    {
        if (!empty($this->placeholders) && !empty($object)) {
            foreach ($messages as $arrayKey => $message) {
                if ($message === null) {
                    $messages[$arrayKey] = "";
                }
            }
            foreach ($this->placeholders as $placeholder) {
                if (isset($object->{$placeholder->placeholder})) {
                    $value = $object->{$placeholder->placeholder};
                } else if (!empty($placeholderMiddle)) {
                    $value = null;
                    $keyWord = explode($placeholderMiddle, $placeholder->placeholder);
                    $size = sizeof($keyWord);

                    if ($size === 1) {
                        switch ($keyWord[0]) {
                            case "staticKnowledge":
                                $value = $this->plan->knowledge->getStatic($object->userID, 0, false);
                                break;
                            case "dynamicKnowledge":
                                $value = $this->plan->knowledge->getDynamic($object->userID, 0, false);
                                break;
                            case "allKnowledge":
                                $value = $this->plan->knowledge->getAll($object->userID, 0, false);
                                break;
                            case "botReplies":
                                $value = $this->plan->conversation->getReplies($object->userID, 0, false);
                                break;
                            case "botMessages":
                                $value = $this->plan->conversation->getMessages($object->userID, 0, false);
                                break;
                            case "allMessages":
                                $value = $this->plan->conversation->getConversation($object->userID, 0, false);
                                break;
                            default:
                                break;
                        }
                    } else if ($size === 2 && is_numeric($keyWord[1])) {
                        switch ($keyWord[0]) {
                            case "staticKnowledge":
                                $value = $this->plan->knowledge->getStatic($object->userID, $keyWord[1], false);
                                break;
                            case "dynamicKnowledge":
                                $value = $this->plan->knowledge->getDynamic($object->userID, $keyWord[1], false);
                                break;
                            case "allKnowledge":
                                $value = $this->plan->knowledge->getAll($object->userID, $keyWord[1], false);
                                break;
                            case "botReplies":
                                $value = $this->plan->conversation->getReplies($object->userID, $keyWord[1], false);
                                break;
                            case "botMessages":
                                $value = $this->plan->conversation->getMessages($object->userID, $keyWord[1], false);
                                break;
                            case "allMessages":
                                $value = $this->plan->conversation->getConversation($object->userID, $keyWord[1], false);
                                break;
                            default:
                                break;
                        }
                    }
                } else {
                    $value = null;
                }

                if ($value !== null) {
                    if (is_array($value)) {
                        $array = $value;
                        $value = "";

                        foreach ($array as $row) {
                            $value .= $row . DiscordProperties::NEW_LINE;
                        }
                        if (!empty($value)) {
                            $value = substr($value, 0, -strlen(DiscordProperties::NEW_LINE));
                        }
                    }
                    $object->placeholderArray[] = $value;

                    if ($placeholder->include_previous !== null
                        && $placeholder->include_previous > 0) {
                        $size = sizeof($object->placeholderArray);

                        for ($position = 1; $position <= $placeholder->include_previous; $position++) {
                            $modifiedPosition = $size - $position;

                            if ($modifiedPosition >= 0) {
                                $positionValue = $object->placeholderArray[$modifiedPosition];

                                foreach ($messages as $arrayKey => $message) {
                                    if (!empty($message)) {
                                        $messages[$arrayKey] = str_replace(
                                            $placeholderStart . $placeholder->placeholder . $placeholderEnd,
                                            $positionValue,
                                            $message
                                        );
                                    }
                                }
                            } else {
                                break;
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
        }
        return $messages;
    }

    public function build(object $object): ?string
    {
        if (!empty($this->instructions)) {
            $information = "";
            $disclaimer = "";

            foreach ($this->instructions as $instruction) {
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
            if ($this->plan->strictReply) {
                $information .= DiscordProperties::NEW_LINE . DiscordProperties::NEW_LINE
                    . ($this->plan->requireMention ? DiscordProperties::STRICT_REPLY_INSTRUCTIONS_WITH_MENTION : DiscordProperties::STRICT_REPLY_INSTRUCTIONS);
            }
            return $information
                . (!empty($disclaimer)
                    ? DiscordSyntax::HEAVY_CODE_BLOCK . $disclaimer . DiscordSyntax::HEAVY_CODE_BLOCK
                    : "");
        }
        return null;
    }

    public function getObject($serverID, $serverName,
                              $channelID, $channelName,
                              $threadID, $threadName,
                              $userID, $userName,
                              $messageContent, $messageID,
                              $botID, $botName): object
    {
        $object = new stdClass();
        $object->serverID = $serverID;
        $object->serverName = $serverName;
        $object->channelID = $channelID;
        $object->channelName = $channelName;
        $object->threadID = $threadID;
        $object->threadName = $threadName;
        $object->userID = $userID;
        $object->userName = $userName;
        $object->messageContent = $messageContent;
        $object->messageID = $messageID;
        $object->botID = $botID;
        $object->botName = $botName;

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
}