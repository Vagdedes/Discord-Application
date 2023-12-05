<?php

use Discord\Builders\MessageBuilder;
use Discord\Parts\Embed\Embed;
use Discord\Parts\Guild\Guild;
use Discord\Parts\Thread\Thread;

class DiscordLogs
{

    private ?DiscordBot $bot;
    private array $channels;
    private int $ignoreAction;

    public function __construct(?DiscordBot $bot)
    {
        $this->bot = $bot;
        $this->ignoreAction = 0;
        $this->channels = $bot === null
            ? array()
            : get_sql_query(
                BotDatabaseTable::BOT_CHANNEL_LOGS,
                null,
                array(
                    array("bot_id", $this->bot?->botID),
                    array("deletion_date", null),
                    null,
                    array("expiration_date", "IS", null, 0),
                    array("expiration_date", ">", get_current_date()),
                    null
                )
            );
    }

    public function logInfo(?Guild          $guild,
                            int|string|null $userID, ?string $action,
                            mixed           $object, mixed $oldObject = null): bool
    {
        $hasGuild = $guild !== null;

        if ($this->ignoreAction > 0) {
            $this->ignoreAction--;
        } else {
            check_clear_memory();
            $date = get_current_date();
            $hasObjectParameter = $object !== null;
            $hasOldObjectParameter = $oldObject !== null;
            $encodedObject = $hasObjectParameter ? json_encode($object) : null;
            $encodedOldObject = $hasOldObjectParameter ? json_encode($oldObject) : null;

            if (sql_insert(
                    BotDatabaseTable::BOT_LOGS,
                    array(
                        "bot_id" => $this->bot?->botID,
                        "server_id" => $hasGuild ? $guild->id : null,
                        "user_id" => $userID,
                        "action" => $action,
                        "object" => $encodedObject,
                        "old_object" => $encodedOldObject,
                        "creation_date" => $date
                    )
                )
                && $hasGuild
                && $this->bot !== null
                && ($hasObjectParameter || $hasOldObjectParameter)
                && !empty($this->channels)) {
                $hasArray = is_array($object);
                $hasOldArray = is_array($oldObject);
                $hasObject = is_object($object);
                $hasOldObject = is_object($oldObject);

                if ($hasArray || $hasObject
                    || $hasOldArray || $hasOldObject) {
                    $object = $hasArray ? $object
                        : ($hasObject ? json_decode($encodedObject, true) : array());
                    $oldObject = $hasOldArray ? $oldObject
                        : ($hasOldObject ? json_decode($encodedOldObject, true) : array());

                    foreach ($this->channels as $row) {
                        if (($row->action === null || $row->action == $action)
                            && $row->server_id == $guild->id) {
                            $channel = $this->bot->discord->getChannel($row->channel_id);

                            if ($channel !== null
                                && $channel->guild_id == $row->server_id) {
                                if ($row->thread_id === null) {
                                    if ($channel->allowText()) {
                                        $channel->sendMessage(
                                            $this->prepareLogMessage($row, $date, $userID, $action, $object, $oldObject)
                                        );
                                    }
                                } else {
                                    foreach ($channel->threads as $thread) {
                                        if ($thread instanceof Thread
                                            && $row->thread_id == $thread->id) {
                                            $thread->sendMessage(
                                                $this->prepareLogMessage(
                                                    $row, $date,
                                                    $userID, $action,
                                                    $object, $oldObject
                                                )
                                            );
                                            break;
                                        }
                                    }
                                }
                            }
                            break;
                        }
                    }
                }
            }
        }
        if ($hasGuild) {
            foreach ($this->bot->plans as $plan) {
                $plan->userLevels->trackVoiceChannels($guild);
            }
        }
        return $this->bot !== null && $this->bot->refresh();
    }

    private function prepareLogMessage(object          $row, string $date,
                                       int|string|null $userID, ?string $action,
                                       mixed           $object, mixed $oldObject,
                                       MessageBuilder  $prepared = null): MessageBuilder
    {
        $syntaxExtra = strlen(DiscordSyntax::HEAVY_CODE_BLOCK) * 2;
        $this->ignoreAction++;
        $counter = 0;
        $message = $prepared !== null ? $prepared : MessageBuilder::new();

        foreach (array_chunk(
                     $object,
                     DiscordInheritedLimits::MAX_FIELDS_PER_EMBED,
                     true
                 ) as $chunk) {
            $counter++;
            $embed = new Embed($this->bot->discord);

            if ($userID !== null) {
                $user = $this->bot->utilities->getUser($userID);

                if ($user !== null) {
                    $embed->setAuthor($user->username, $user->avatar);
                } else {
                    $embed->setAuthor($userID);
                }
            }
            if ($row->color !== null) {
                $embed->setColor($row->color);
            }
            if ($row->description !== null) {
                $embed->setFooter($row->description);
            }
            $embed->setTitle($this->beautifulText($action));
            $embed->setTimestamp(strtotime($date));

            foreach ($chunk as $arrayKey => $arrayValue) {
                if (!empty($arrayValue)) {
                    if (is_object($arrayValue)) {
                        $arrayValue = json_decode(json_encode($arrayValue), true);
                    }
                    if (is_array($arrayValue)) {
                        $arrayValue = implode("\n", array_map(
                            function ($key, $value) {
                                if (is_array($value) || is_object($value)) {
                                    return $this->beautifulText($key) . ": " .
                                        json_encode($value);
                                } else {
                                    return $this->beautifulText($key) . ": "
                                        . (is_bool($value)
                                            ? ($value ? "true" : "false")
                                            : ($value == null ? "null" : $value));
                                }
                            },
                            array_keys($arrayValue),
                            $arrayValue
                        ));
                    }
                    $arrayKey = str_replace(DiscordSyntax::HEAVY_CODE_BLOCK, "", $arrayKey);
                    $arrayValue = str_replace(DiscordSyntax::HEAVY_CODE_BLOCK, "", $arrayValue);
                    $embed->addFieldValues(
                        substr($this->beautifulText($arrayKey), 0,
                            DiscordInheritedLimits::MAX_FIELD_KEY_LENGTH),
                        DiscordSyntax::HEAVY_CODE_BLOCK
                        . substr($arrayValue, 0,
                            DiscordInheritedLimits::MAX_FIELD_VALUE_LENGTH - $syntaxExtra)
                        . DiscordSyntax::HEAVY_CODE_BLOCK
                    );
                }
            }
            $message->addEmbed($embed);

            if ($counter === DiscordInheritedLimits::MAX_EMBEDS_PER_MESSAGE) {
                return $message;
            }
        }
        return $oldObject !== null
            ? $this->prepareLogMessage($row, $date, $userID, $action, $oldObject, null, $message)
            : $message;
    }

    private function beautifulText(string $string): string
    {
        return str_replace("_", "-", strtolower($string));
    }

    public function logError(int|string|null $planID, mixed $object, bool $exit = false): void
    {
        sql_insert(
            BotDatabaseTable::BOT_ERRORS,
            array(
                "bot_id" => $this->bot?->botID,
                "plan_id" => $planID,
                "object" => $object !== null ? json_encode($object) : null,
                "creation_date" => get_current_date()
            )
        );
        if ($exit) {
            exit($object);
        } else {
            var_dump($object);
        }
    }

}