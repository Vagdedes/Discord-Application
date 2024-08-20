<?php

use Discord\Builders\MessageBuilder;
use Discord\Parts\Channel\Channel;
use Discord\Parts\Channel\Invite;
use Discord\Parts\Channel\Message;
use Discord\Parts\Embed\Embed;
use Discord\Parts\Guild\Guild;
use Discord\Parts\Part;
use Discord\Parts\Thread\Thread;
use Discord\Parts\User\Member;
use Discord\WebSockets\Event;

class DiscordLogs
{

    private ?DiscordBot $bot;
    private array $channels;
    private int $ignoreAction;

    public const GUILD_MEMBER_ADD_VIA_INVITE = 'GUILD_MEMBER_ADD_VIA_INVITE';

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

    /**
     * @throws Exception
     */
    public function logInfo(Guild|int|string|null $guild,
                            int|string|null       $userID, ?string $action,
                            mixed                 $object, mixed $oldObject = null,
                            bool                  $refresh = true): bool
    {
        if ($guild !== null && !($guild instanceof Guild)) {
            foreach ($this->bot?->discord->guilds as $guildFound) {
                if ($guildFound->id == $guild) {
                    $guild = $guildFound;
                    break;
                }
            }
        }
        $hasGuild = $guild !== null;

        if ($this->ignoreAction > 0 && $action === Event::MESSAGE_CREATE) {
            $this->ignoreAction--;
        } else {
            $date = get_current_date();
            $hasObjectParameter = $object !== null;
            $hasOldObjectParameter = $oldObject !== null;
            $encodedObject = $hasObjectParameter
                ? ($object instanceof Part ? @json_encode($object->jsonSerialize()) : @json_encode($object))
                : null;
            $encodedOldObject = $hasOldObjectParameter
                ? ($oldObject instanceof Part ? @json_encode($oldObject->jsonSerialize()) : @json_encode($oldObject))
                : null;

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
                foreach ($this->channels as $row) {
                    if (($row->action === null || $row->action == $action)
                        && $row->server_id == $guild->id) {
                        $channel = $this->bot->discord->getChannel($row->channel_id);

                        if ($channel !== null
                            && $channel->guild_id == $row->server_id) {
                            $messagesToSendCallable = function ($oldObject) use ($row, $date, $userID, $action, $object, $channel) {
                                if ($row->thread_id === null) {
                                    if ($channel->allowText()
                                        && ($row->ignore_bot === null
                                            || $userID != $this->bot->botID)) {
                                        $messageBuilder = $this->prepareLogMessage(
                                            $row, $date,
                                            $userID, $action,
                                            $object, $oldObject,
                                            MessageBuilder::new()
                                        );

                                        if ($messageBuilder !== null) {
                                            $channel->sendMessage($messageBuilder);
                                        }
                                    }
                                } else if (!empty($channel->threads->first())) {
                                    foreach ($channel->threads as $thread) {
                                        if ($thread instanceof Thread
                                            && $row->thread_id == $thread->id
                                            && ($row->ignore_bot === null
                                                || $userID != $this->bot->botID)) {
                                            $messageBuilder = $this->prepareLogMessage(
                                                $row, $date,
                                                $userID, $action,
                                                $object, $oldObject,
                                                MessageBuilder::new()
                                            );

                                            if ($messageBuilder !== null) {
                                                $thread->sendMessage($messageBuilder);
                                            }
                                            break;
                                        }
                                    }
                                }
                            };

                            if ($action === self::GUILD_MEMBER_ADD_VIA_INVITE) {
                                $oldInvites = DiscordInviteTracker::getInvites($guild);
                                $callable = function () use ($oldInvites, $guild, $messagesToSendCallable, $oldObject) {
                                    $newInvites = DiscordInviteTracker::getInvites($guild);

                                    if (empty($oldInvites)) {
                                        if (!empty($newInvites)) {
                                            foreach ($newInvites as $invite) {
                                                $oldObject = $invite;
                                                break;
                                            }
                                        }
                                    } else if (!empty($newInvites)) {
                                        foreach ($newInvites as $invite) {
                                            $comparisonInvite = $oldInvites[$invite->code] ?? null;

                                            if ($comparisonInvite === null) {
                                                $oldObject = $invite;
                                                break;
                                            } else if ($comparisonInvite->uses < $invite->uses) {
                                                $oldObject = $invite;
                                                break;
                                            }
                                        }
                                    }
                                    $messagesToSendCallable($oldObject);
                                };
                                DiscordInviteTracker::track($guild, null, $callable);
                            } else {
                                $messagesToSendCallable($oldObject);
                            }
                        }
                        break;
                    }
                }
            }
        }
        if ($hasGuild) {
            foreach ($this->bot->plans as $plan) {
                $plan->userLevels->trackVoiceChannels($guild);
            }
        }
        return $refresh && $this->bot !== null && $this->bot->refresh();
    }

    private function prepareLogMessage(object          $row, string $date,
                                       int|string|null $userID, ?string $action,
                                       mixed           $object, mixed $oldObject,
                                       MessageBuilder  $message): ?MessageBuilder
    {
        $syntaxExtra = strlen(DiscordSyntax::HEAVY_CODE_BLOCK) * 2;
        $this->ignoreAction++;
        $counter = 0;
        $loopObject = is_object($object)
            ? (method_exists($object, "getRawAttributes") ? $object->getRawAttributes() : json_decode(@json_encode($object), true))
            : (is_array($object) ? $object : array());

        foreach (array_chunk(
                     $loopObject,
                     DiscordInheritedLimits::MAX_FIELDS_PER_EMBED,
                     true
                 ) as $chunk) {
            unset($chunk["guild_id"]);
            unset($chunk["guild"]);

            if (!empty($chunk)) {
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

                if ($object instanceof Message) {
                    $embed->setDescription($object->link);
                } else if ($object instanceof Member) {
                    $embed->setDescription("<@" . $object->id . ">");
                } else if ($object instanceof Channel) {
                    if ($this->bot->channels->isBlacklisted(null, $object)) {
                        return null;
                    }
                    $embed->setDescription("<#" . $object->id . ">");
                } else if ($object instanceof Thread) {
                    if ($this->bot->channels->isBlacklisted(null, $object)) {
                        return null;
                    }
                    $embed->setDescription("<#" . $object->id . ">");
                } else if ($object instanceof Invite) {
                    $embed->setDescription($object->invite_url
                        . "\nIn: <#" . $object->channel_id . ">"
                        . "\nBy: <@" . $object->inviter->id . ">");
                }
                $embed->setTimestamp(strtotime($date));

                foreach ($chunk as $arrayKey => $arrayValue) {
                    if (!empty($arrayValue)) {
                        if (is_object($arrayValue)) {
                            $arrayValue = json_decode(@json_encode($arrayValue), true);
                        }
                        if (is_array($arrayValue)) {
                            unset($arrayValue["guild_id"]);
                            unset($arrayValue["guild"]);

                            foreach ($arrayValue as $key => $value) {
                                if ($value === null) {
                                    unset($arrayValue[$key]);
                                }
                            }
                            $arrayValue = implode("\n", array_map(
                                function ($key, $value) {
                                    if (is_array($value) || is_object($value)) {
                                        $show = @json_encode($value);
                                        return $this->beautifulText($key) . ": " . (strlen($show) > 100 ? "(REDACTED)" : $show);
                                    } else {
                                        return $this->beautifulText($key) . ": "
                                            . (is_bool($value)
                                                ? ($value ? "true" : "false")
                                                : $value);
                                    }
                                },
                                array_keys($arrayValue),
                                $arrayValue
                            ));
                        }
                        if ($arrayValue !== null) {
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
                }
                $message->addEmbed($embed);

                if ($counter === DiscordInheritedLimits::MAX_EMBEDS_PER_MESSAGE) {
                    return $message;
                }
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

    public function logError(int|string|object|null $plan, mixed $object, bool $exit = false): void
    {
        sql_insert(
            BotDatabaseTable::BOT_ERRORS,
            array(
                "bot_id" => $this->bot?->botID,
                "plan_id" => $plan instanceof DiscordPlan ? $plan->planID : $plan,
                "object" => $object !== null ? @json_encode($object) : null,
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