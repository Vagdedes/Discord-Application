<?php

use Discord\Parts\Channel\Channel;
use Discord\Parts\Thread\Thread;
use Discord\Parts\User\Member;
use Discord\Parts\User\User;

class DiscordMute
{
    private DiscordBot $bot;
    private array $voice, $text, $command, $all, $merged;

    public const
        COMMAND = "command",
        VOICE = "voice",
        TEXT = "text",
        ALL = null;

    public function __construct(DiscordBot $bot)
    {
        $date = get_current_date();
        $this->bot = $bot;
        $this->all = get_sql_query(
            BotDatabaseTable::BOT_MUTE,
            null,
            array(
                array("type", null),
                array("deletion_date"),
                null,
                array("expiration_date", "IS", null, 0),
                array("expiration_date", ">", $date),
                null
            )
        );
        $this->voice = get_sql_query(
            BotDatabaseTable::BOT_MUTE,
            null,
            array(
                array("type", self::VOICE),
                array("deletion_date"),
                null,
                array("expiration_date", "IS", null, 0),
                array("expiration_date", ">", $date),
                null
            )
        );
        $this->text = get_sql_query(
            BotDatabaseTable::BOT_MUTE,
            null,
            array(
                array("type", self::TEXT),
                array("deletion_date"),
                null,
                array("expiration_date", "IS", null, 0),
                array("expiration_date", ">", $date),
                null
            )
        );
        $this->command = get_sql_query(
            BotDatabaseTable::BOT_MUTE,
            null,
            array(
                array("type", self::COMMAND),
                array("deletion_date"),
                null,
                array("expiration_date", "IS", null, 0),
                array("expiration_date", ">", $date),
                null
            )
        );
        $this->refresh();
    }

    public function isMuted(User|Member $member, Channel|Thread $thread,
                            ?string     $type = self::ALL): ?object
    {
        if (!empty($this->merged)) {
            $date = get_current_date();
            $all = $type == self::ALL;

            foreach ($this->merged as $mute) {
                if ($mute->user_id == $member->id
                    && ($all || $mute->type == $type)
                    && ($mute->channel_id == $thread->id || $mute->channel_id === null)
                    && ($mute->expiration_date === null || $mute->expiration_date > $date)) {
                    return $mute;
                }
            }
        }
        return null;
    }

    public function wasMuted(User|Member $member, Channel|Thread $thread,
                             ?string     $type = self::ALL): bool
    {
        return false;
    }

    public function mute(User|Member    $self, User|Member $member,
                         Channel|Thread $channelOrThread, string $reason,
                         ?string        $type = self::ALL, bool $specific = false,
                         ?string        $expiration = null): array|null
    {
        $mute = $this->isMuted($member, $channelOrThread, $type);

        if ($mute !== null) {
            return array(true, $mute);
        }
        $insert = array(
            "type" => $type,
            "user_id" => $member->id,
            "server_id" => $channelOrThread->guild_id,
            "channel_id" => $specific ? null : ($channelOrThread instanceof Thread ? $channelOrThread->parent_id : $channelOrThread->id),
            "thread_id" => !$specific && $channelOrThread instanceof Thread ? $channelOrThread->id : null,
            "creation_date" => get_current_date(),
            "creation_reason" => $reason,
            "created_by" => $self->id,
            "expiration_date" => $expiration !== null ? get_future_date($expiration) : null
        );

        if (sql_insert(BotDatabaseTable::BOT_MUTE, $insert)) {
            $insert["expiration_reason"] = null;
            $insert["deletion_date"] = null;
            $insert["deletion_reason"] = null;
            $insert["deleted_by"] = null;
            $object = new stdClass();

            foreach ($insert as $key => $value) {
                $object->{$key} = $value;
            }
            switch ($type) {
                case self::VOICE:
                    $this->voice[] = $object;
                    break;
                case self::TEXT:
                    $this->text[] = $object;
                    break;
                default:
                    $this->all[] = $object;
                    break;
            }
            $this->refresh();
            return array(false, $object);
        } else {
            return array(false, "Failed to insert mute to the database.");
        }
    }

    public function unmute(User|Member    $self, User|Member $member,
                           Channel|Thread $channelOrThread, string $reason,
                           ?string        $type = self::ALL, bool $specific = false): array
    {
        if (!empty($this->merged)) {
            $array = array();
            $date = get_current_date();
            $all = $type == self::ALL;

            foreach ($this->merged as $mute) {
                if ($mute->user_id == $member->id
                    && ($all || $mute->type == $type)
                    && ($mute->channel_id == $channelOrThread->id || !$specific && $mute->channel_id === null)
                    && ($mute->expiration_date === null || $mute->expiration_date > $date)) {
                    if (set_sql_query(
                        BotDatabaseTable::BOT_MUTE,
                        array(
                            "deletion_date" => $date,
                            "deletion_reason" => $reason,
                            "deleted_by" => $self->id
                        ),
                        array(
                            array("id", $mute->id)
                        ),
                        null,
                        1
                    )) {
                        $array[] = array(true, $mute);

                        switch ($mute->type) {
                            case self::VOICE:
                                unset($this->voice[array_search($mute, $this->voice)]);
                                break;
                            case self::TEXT:
                                unset($this->text[array_search($mute, $this->text)]);
                                break;
                            default:
                                unset($this->all[array_search($mute, $this->all)]);
                                break;
                        }
                    } else {
                        $array[] = array(false, "Failed to update mute in the database.");
                    }
                }
            }

            if (!empty($array)) {
                $this->refresh();
            }
            return $array;
        } else {
            return array();
        }
    }

    private function refresh(): void
    {
        $this->merged = array_merge($this->all, $this->voice, $this->text, $this->command);
    }
}