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
                array("plan_id", $this->plan->planID),
                array("deletion_date", null),
                null,
                array("expiration_date", "IS", null, 0),
                array("expiration_date", ">", get_current_date()),
                null
            )
        );
        $this->placeholders = get_sql_query(
            BotDatabaseTable::BOT_INSTRUCTION_PLACEHOLDERS,
            null,
            array(
                array("plan_id", $this->plan->planID),
                array("deletion_date", null),
                null,
                array("expiration_date", "IS", null, 0),
                array("expiration_date", ">", get_current_date()),
                null
            )
        );
    }

    public function build($serverID, $channelID, $userID, $message, $botID): ?string
    {
        if (!empty($this->instructions)) {
            $hasPlaceholders = !empty($this->placeholders);
            $placeholderArray = array();
            $information = "";
            $disclaimer = "";
            $object = new stdClass();
            $object->serverID = $serverID;
            $object->channelID = $channelID;
            $object->userID = $userID;
            $object->message = $message;
            $object->botID = $botID;
            $object->newLine = "\n";
            $object->messageRetention = $this->plan->messageRetention;
            $object->messageCooldown = $this->plan->messageCooldown;
            $object->punishmentTypes = $this->plan->moderation->punishmentTypes;

            foreach ($this->instructions as $instruction) {
                $placeholderStart = $instruction->placeholder_start;
                $placeholderEnd = $instruction->placeholder_end;
                $rowInformation = $instruction->information;
                $rowDisclaimer = $instruction->disclaimer;

                if ($hasPlaceholders) {
                    foreach ($this->placeholders as $placeholder) {
                        if (isset($object->{$placeholder->placeholder})) {
                            $value = $object->{$placeholder->placeholder};
                        } else {
                            $value = null;
                            $keyWord = explode($placeholder->placeholder_middle, $placeholder->placeholder);
                            $size = sizeof($keyWord);

                            if ($size === 1) {
                                switch ($keyWord[0]) {
                                    case "staticKnowledge":
                                        $value = $this->plan->knowledge->getStatic($userID, 0, false);
                                        break;
                                    case "dynamicKnowledge":
                                        $value = $this->plan->knowledge->getDynamic($userID, 0, false);
                                        break;
                                    case "allKnowledge":
                                        $value = $this->plan->knowledge->getAll($userID, 0, false);
                                        break;
                                    case "botReplies":
                                        $value = $this->plan->conversation->getReplies($userID, 0, false);
                                        break;
                                    case "botMessages":
                                        $value = $this->plan->conversation->getMessages($userID, 0, false);
                                        break;
                                    case "allMessages":
                                        $value = $this->plan->conversation->getConversation($userID, 0, false);
                                        break;
                                    default:
                                        break;
                                }
                            } else if ($size === 2 && is_numeric($keyWord[1])) {
                                switch ($keyWord[0]) {
                                    case "staticKnowledge":
                                        $value = $this->plan->knowledge->getStatic($userID, $keyWord[1], false);
                                        break;
                                    case "dynamicKnowledge":
                                        $value = $this->plan->knowledge->getDynamic($userID, $keyWord[1], false);
                                        break;
                                    case "allKnowledge":
                                        $value = $this->plan->knowledge->getAll($userID, $keyWord[1], false);
                                        break;
                                    case "botReplies":
                                        $value = $this->plan->conversation->getReplies($userID, $keyWord[1], false);
                                        break;
                                    case "botMessages":
                                        $value = $this->plan->conversation->getMessages($userID, $keyWord[1], false);
                                        break;
                                    case "allMessages":
                                        $value = $this->plan->conversation->getConversation($userID, $keyWord[1], false);
                                        break;
                                    default:
                                        break;
                                }
                            }
                        }

                        if ($value !== null) {
                            if (is_array($value)) {
                                $array = $value;
                                $value = "";

                                foreach ($array as $row) {
                                    $value .= $row . $object->newLine;
                                }
                                if (!empty($value)) {
                                    $value = substr($value, 0, -strlen($object->newLine));
                                }
                            }
                            $placeholderArray[] = $value;

                            if ($placeholder->include_previous !== null
                                && $placeholder->include_previous > 0) {
                                $size = sizeof($placeholderArray);

                                for ($position = 1; $position <= $placeholder->include_previous; $position++) {
                                    $modifiedPosition = $size - $position;

                                    if ($modifiedPosition >= 0) {
                                        $positionValue = $placeholderArray[$modifiedPosition];
                                        $rowInformation = str_replace(
                                            $placeholderStart . $placeholder->placeholder . $placeholderEnd,
                                            $positionValue,
                                            $rowInformation
                                        );
                                        $rowDisclaimer = str_replace(
                                            $placeholderStart . $placeholder->placeholder . $placeholderEnd,
                                            $positionValue,
                                            $rowDisclaimer
                                        );
                                    } else {
                                        break;
                                    }
                                }
                            }
                            $rowInformation = str_replace(
                                $placeholderStart . $placeholder->placeholder . $placeholderEnd,
                                $value,
                                $rowInformation
                            );
                            $rowDisclaimer = str_replace(
                                $placeholderStart . $placeholder->placeholder . $placeholderEnd,
                                $value,
                                $rowDisclaimer
                            );
                        }
                    }
                }
                $information .= $rowInformation;
                $disclaimer .= $rowDisclaimer;
            }
            return $information . (!empty($disclaimer)
                    ? DiscordSyntax::HEAVY_CODE_BLOCK . $disclaimer . DiscordSyntax::HEAVY_CODE_BLOCK
                    : "");
        }
        return null;
    }
}