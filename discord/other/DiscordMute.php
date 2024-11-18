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
                array("type", self::ALL),
                array("deletion_date", null),
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
                array("deletion_date", null),
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
                array("deletion_date", null),
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
                array("deletion_date", null),
                null,
                array("expiration_date", "IS", null, 0),
                array("expiration_date", ">", $date),
                null
            )
        );
        $this->refresh();
    }

    public function isMuted(User|Member $member, Channel|Thread|null $channelOrThread,
                            ?string     $type = self::ALL): ?object
    {
        if (!empty($this->merged)) {
            $date = get_current_date();
            $all = $type === self::ALL;
            $specific = $channelOrThread !== null;

            foreach ($this->merged as $mute) {
                if ($mute->user_id == $member->id
                    && $mute->server_id == $member->guild_id
                    && ($all || $mute->type == $type)
                    && ($specific && $mute->channel_id == $channelOrThread->id || $mute->channel_id === null)
                    && ($mute->expiration_date === null || $mute->expiration_date > $date)) {
                    return $mute;
                }
            }
        }
        return null;
    }

    public function wasMuted(User|Member $member, Channel|Thread $channelOrThread,
                             ?string     $type = self::ALL): bool
    {
        $isThread = $channelOrThread instanceof Thread;
        return !empty(get_sql_query(
            BotDatabaseTable::BOT_MUTE,
            null,
            array(
                array("deletion_date", null),
                array("user_id", $member->id),
                $isThread ? null : "",
                $isThread ? array("thread_id", "IS", null, 0) : "",
                $isThread ? array("thread_id", $channelOrThread->id) : "",
                $isThread ? null : array("thread_id", null), // Attention
                null,
                array("channel_id", "IS", null, 0),
                array("channel_id", $isThread ? $channelOrThread->parent_id : $channelOrThread->id),
                null,
                null,
                array("type", "IS", null, 0),
                array("type", $type),
                null,
                null,
                array("expiration_date", "IS", null, 0),
                array("expiration_date", ">", get_current_date()),
                null
            )
        ));
    }

    public function mute(Member|User         $self, Member $member,
                         Channel|Thread|null $channelOrThread, string $reason,
                         ?string             $type = self::ALL,
                         ?string             $expiration = null): array
    {
        $mute = $this->isMuted($member, $channelOrThread, $type);

        if ($mute !== null) {
            return array(true, $mute);
        }
        $specific = $channelOrThread !== null;
        $insert = array(
            "type" => $type,
            "user_id" => $member->id,
            "server_id" => $member->guild_id,
            "channel_id" => $specific ? ($channelOrThread instanceof Thread ? $channelOrThread->parent_id : $channelOrThread->id) : null,
            "thread_id" => $specific && $channelOrThread instanceof Thread ? $channelOrThread->id : null,
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

    public function unmute(Member|User         $self, Member $member,
                           Channel|Thread|null $channelOrThread, string $reason,
                           ?string             $type = self::ALL): array
    {
        if (!empty($this->merged)) {
            $array = array();
            $date = get_current_date();
            $all = $type === self::ALL;
            $specific = $channelOrThread !== null;

            foreach ($this->merged as $mute) {
                if ($mute->user_id == $member->id
                    && $mute->server_id == $member->guild_id
                    && ($all || $mute->type == $type)
                    && ($specific && $mute->channel_id == $channelOrThread->id || $mute->channel_id === null)
                    && ($mute->expiration_date === null || $mute->expiration_date > $date)) {
                    $where = isset($mute->id)
                        ? array("id", $mute->id)
                        : array(
                            array("user_id", $mute->user_id),
                            array("type", $mute->type),
                            array("creation_date", $mute->creation_date),
                            array("created_by", $mute->created_by),
                            array("expiration_date", $mute->expiration_date)
                        );

                    if (set_sql_query(
                        BotDatabaseTable::BOT_MUTE,
                        array(
                            "deletion_date" => $date,
                            "deletion_reason" => $reason,
                            "deleted_by" => $self->id
                        ),
                        $where,
                        array(
                            "DESC",
                            "id"
                        ),
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