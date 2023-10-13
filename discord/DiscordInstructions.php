<?php

class DiscordInstructions
{

    private DiscordPlan $plan;
    private array $instructions;
    private array $placeholders;

    public function __construct(DiscordPlan $plan)
    {
        $this->plan = $plan;
        $this->refresh();
    }

    public function refresh(): void
    {
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
            //todo add more (user message history, bot message history, static knowledge, dynamic knowledge)

            foreach ($this->instructions as $instruction) {
                $placeholderStart = $instruction->placeholder_start;
                $placeholderEnd = $instruction->placeholder_end;
                $rowInformation = $instruction->information;
                $rowDisclaimer = $instruction->disclaimer;

                if ($hasPlaceholders) {
                    foreach ($this->placeholders as $placeholder) {
                        if (isset($object->{$placeholder->placeholder})) {
                            $value = $object->{$placeholder->placeholder};
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