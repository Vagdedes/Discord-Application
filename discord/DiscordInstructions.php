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
                array("plan_id", $this->plan->planID),
                null,
                null,
                array("expiration_date", "IS", null, 0),
                array("expiration_date", ">", get_current_date()),
                null
            ),
            array(
                "ASC",
                "plan_id"
            )
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

    public function replace(array $messages, $object = null,
                                  $placeholderStart = DiscordProperties::DEFAULT_PLACEHOLDER_START,
                                  $placeholderMiddle = DiscordProperties::DEFAULT_PLACEHOLDER_MIDDLE,
                                  $placeholderEnd = DiscordProperties::DEFAULT_PLACEHOLDER_END): array
    {
        if (!empty($this->placeholders) && !empty($object)) {
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
                                    $messages[$arrayKey] = str_replace(
                                        $placeholderStart . $placeholder->placeholder . $placeholderEnd,
                                        $positionValue,
                                        $message
                                    );
                                }
                            } else {
                                break;
                            }
                        }
                    }
                    foreach ($messages as $arrayKey => $message) {
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

    public function build($object): ?string
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
            return $information . (!empty($disclaimer)
                    ? DiscordSyntax::HEAVY_CODE_BLOCK . $disclaimer . DiscordSyntax::HEAVY_CODE_BLOCK
                    : "");
        }
        return null;
    }

    public function getObject($serverID, $channelID, $threadID,
                              $userID, $messageContent, $messageID, $botID): object
    {
        $object = new stdClass();
        $object->serverID = $serverID;
        $object->channelID = $channelID;
        $object->threadID = $threadID;
        $object->userID = $userID;
        $object->messageContent = $messageContent;
        $object->messageID = $messageID;
        $object->botID = $botID;
        $object->newLine = DiscordProperties::NEW_LINE;
        $object->messageRetention = $this->plan->messageRetention;
        $object->messageCooldown = $this->plan->messageCooldown;
        $object->punishmentTypes = $this->plan->moderation->punishmentTypes;
        $object->placeholderArray = array();
        return $object;
    }
}