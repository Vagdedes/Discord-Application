<?php

use Discord\Parts\Channel\Channel;
use Discord\Parts\Thread\Thread;
use Discord\Parts\User\Member;
use Discord\Parts\User\User;

class DiscordChannels
{

    private DiscordBot $bot;
    private array $list, $whitelist, $blacklist, $temporary;
    private static array $threadHistory = array();

    public function __construct(DiscordBot $bot)
    {
        $this->bot = $bot;
        $this->temporary = array();
        $this->list = get_sql_query(
            BotDatabaseTable::BOT_CHANNELS,
            null,
            array(
                array("deletion_date", null),
                null,
                array("expiration_date", "IS", null, 0),
                array("expiration_date", ">", get_current_date()),
                null
            ),
            array(
                "DESC",
                "thread_id"
            )
        );

        // Separator

        $this->whitelist = get_sql_query(
            BotDatabaseTable::BOT_CHANNEL_WHITELIST,
            null,
            array(
                array("deletion_date", null),
                null,
                array("expiration_date", "IS", null, 0),
                array("expiration_date", ">", get_current_date()),
                null
            )
        );

        // Separator

        $this->blacklist = get_sql_query(
            BotDatabaseTable::BOT_CHANNEL_BLACKLIST,
            null,
            array(
                array("deletion_date", null),
                null,
                array("expiration_date", "IS", null, 0),
                array("expiration_date", ">", get_current_date()),
                null
            )
        );
    }

    // Separator

    public function getList(): array
    {
        return array_merge($this->list, $this->temporary);
    }

    public function getWhitelist(): array
    {
        return $this->whitelist;
    }

    public function isBlacklisted(object $channel): bool
    {
        if ($channel instanceof Channel || $channel instanceof Thread) {
            $channelID = $this->bot->utilities->getChannel($channel)?->id;
            $threadID = $this->bot->utilities->getThread($channel)?->id;
            $guildID = $channel->guild_id;
            $parentID = $channel instanceof Thread ? $channel->parent->parent_id : $channel->parent_id;
        } else if (isset($channel->channel_id)) {
            $channelID = $channel->channel_id;
            $threadID = null;
            $guildID = $channel?->guild_id;
            $parentID = null;
        } else {
            return false;
        }

        foreach ($this->blacklist as $rowChannel) {
            if (($guildID === null
                    || $rowChannel->server_id == $guildID)
                && ($rowChannel->category_id === null
                    || $rowChannel->category_id == $parentID)
                && ($rowChannel->channel_id === null
                    || $rowChannel->channel_id == $channelID)
                && ($rowChannel->thread_id === null
                    || $rowChannel->thread_id == $threadID)) {
                return true;
            }
        }
        return false;
    }

    public function getIfHasAccess(Channel|Thread $channel, Member|User $member): ?object
    {
        $result = false;
        $list = $this->getList();

        if (!empty($list)) {
            $parent = $channel instanceof Thread ? $channel->parent_id : $channel->id;

            foreach ($list as $channelRow) {
                if ($channelRow->server_id == $channel->guild_id
                    && ($channelRow->category_id === null
                        || $channelRow->category_id == $channel->parent_id)
                    && ($channelRow->channel_id === null
                        || $channelRow->channel_id == $parent)
                    && ($channelRow->thread_id === null
                        || $channelRow->thread_id == $channel->id)) {
                    if ($channelRow->whitelist === null) {
                        $result = $channelRow;
                        break;
                    } else if (!empty($this->whitelist)) {
                        foreach ($this->whitelist as $whitelist) {
                            if ($whitelist->user_id == $member->id
                                && ($whitelist->server_id === null
                                    || $whitelist->server_id == $channel->guild_id
                                    && ($whitelist->category_id === null
                                        || $whitelist->category_id == $channel->parent_id)
                                    && ($whitelist->channel_id === null
                                        || $whitelist->channel_id == $parent)
                                    && ($whitelist->thread_id === null
                                        || $whitelist->thread_id == $channel->id))) {
                                $result = $channelRow;
                                break 2;
                            }
                        }
                    } else {
                        break;
                    }
                }
            }
        }
        return $result === false ? null : $result;
    }

    public function addTemporary(Channel $channel, ?array $properties = null): bool
    {
        if (!array_key_exists($channel->id, $this->temporary)) {
            foreach ($this->list as $rowChannel) {
                if ($rowChannel->server_id == $channel->guild_id
                    && $rowChannel->channel_id == $channel->id) {
                    return false;
                }
            }
            $object = new stdClass();

            while (true) {
                $id = random_number();

                foreach ($this->list as $rowChannel) {
                    if ($rowChannel->id == $id) {
                        continue 2;
                    }
                }
                $object->id = $id;
                break;
            }
            $object->server_id = $channel->guild_id;
            $object->category_id = $channel->parent_id;
            $object->channel_id = $channel->id;
            $object->ai_model_id = null;
            $object->filter = null;
            $object->whitelist = null;
            $object->debug = null;
            $object->require_mention = null;
            $object->not_require_mention_time = null;
            $object->ignore_mention = null;
            $object->feedback = null;
            $object->require_starting_text = null;
            $object->require_contained_text = null;
            $object->require_ending_text = null;
            $object->min_message_length = null;
            $object->max_message_length = null;
            $object->max_attachments_length = null;
            $object->message_cooldown = null;
            $object->message_retention = null;
            $object->prompt_message = null;
            $object->cooldown_message = null;
            $object->failure_message = null;
            $object->welcome_message = null;
            $object->goodbye_message = null;
            $object->creation_date = get_current_date();
            $object->creation_reason = null;
            $object->expiration_date = null;
            $object->expiration_reason = null;
            $object->deletion_date = null;
            $object->deletion_reason = null;
            $object->local_instructions = null;
            $object->public_instructions = null;
            $object->ignore_mention_when_others_mentioned = null;
            $object->ignore_mention_when_no_staff = null;

            if ($properties !== null) {
                foreach ($properties as $key => $value) {
                    $object->{$key} = $value;
                }
            }
            $this->temporary[$channel->id] = $object;
            return true;
        } else {
            return false;
        }
    }

    public function removeTemporary(Channel $channel): bool
    {
        if (array_key_exists($channel->id, $this->temporary)) {
            unset($this->temporary[$channel->id]);
            return true;
        } else {
            return false;
        }
    }

    public static function getAsyncThreadHistory(Channel $channel,
                                                 int     $maxThreads,
                                                 int     $maxMessagesPerThread,
                                                 int     $messagesLength): array
    {
        $hash = array_to_integer(
            array(
                $channel,
                $maxThreads,
                $maxMessagesPerThread
            )
        );
        $array = DiscordChannels::$threadHistory[$hash][$channel->id] ?? array();

        if (!empty($channel->threads->first())) {
            $limit = $maxThreads * $maxMessagesPerThread;
            $lengthCount = 0;

            if ($channel->threads->count() < $maxThreads) {
                // adjust limit based on the number of threads
                $maxMessagesPerThread = ceil($limit / $channel->threads->count());
            }
            foreach ($channel->threads as $thread) {
                $thread->getMessageHistory(
                    [
                        'limit' => (int)$maxMessagesPerThread,
                        'cache' => true
                    ]
                )->done(function ($messageHistory)
                use ($channel, $thread, $maxMessagesPerThread, $hash, &$lengthCount, $messagesLength) {
                    foreach ($messageHistory as $message) {
                        $threadName = $thread->name ?? $thread->id;
                        $message = "'" . $message->author->username
                            . "' in thread '" . $threadName
                            . "' at '" . $message->timestamp->toDateTimeString()
                            . "': " . $message->content;

                        if (!array_key_exists($hash, DiscordChannels::$threadHistory)) {
                            DiscordChannels::$threadHistory[$hash] = array();
                        }
                        if (!array_key_exists($channel->id, DiscordChannels::$threadHistory[$hash])) {
                            DiscordChannels::$threadHistory[$hash][$channel->id] = array();
                        }
                        if (array_key_exists($thread->id, DiscordChannels::$threadHistory[$hash][$channel->id])) {
                            if (sizeof(DiscordChannels::$threadHistory[$hash][$channel->id][$thread->id]) == $maxMessagesPerThread) {
                                array_shift(DiscordChannels::$threadHistory[$hash][$channel->id][$thread->id]);
                            }
                            $lengthCount += strlen($message);

                            if ($lengthCount <= $messagesLength) {
                                DiscordChannels::$threadHistory[$hash][$channel->id][$thread->id][] = $message;
                            } else {
                                break;
                            }
                        } else {
                            $lengthCount += strlen($message);

                            if ($lengthCount <= $messagesLength) {
                                DiscordChannels::$threadHistory[$hash][$channel->id][$thread->id] = array($message);
                            } else {
                                break;
                            }
                        }
                    }
                });
                $maxThreads--;

                if ($maxThreads == 0
                    || $lengthCount > $limit) {
                    break;
                }
            }
        }
        return $array;
    }

}