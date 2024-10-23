<?php

use Discord\Parts\Channel\Channel;
use Discord\Parts\Thread\Thread;
use Discord\Parts\User\Member;
use Discord\Parts\User\User;

class DiscordChannels
{

    private DiscordBot $bot;
    private array $list, $whitelist, $blacklist, $temporary;

    public function __construct(DiscordBot $object)
    {
        $this->bot = $object;
        $this->temporary = array();
        $this->list = array();
        $this->whitelist = array();
        $this->blacklist = array();
        $query = get_sql_query(
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

        if (!empty($query)) {
            foreach ($query as $row) {
                $planID = $row->plan_id ?? 0;

                if (array_key_exists($planID, $this->list)) {
                    $this->list[$planID][] = $row;
                } else {
                    $this->list[$planID] = array($row);
                }
            }
        }

        // Separator

        $query = get_sql_query(
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

        if (!empty($query)) {
            foreach ($query as $row) {
                $planID = $row->plan_id ?? 0;

                if (array_key_exists($planID, $this->whitelist)) {
                    $this->whitelist[$planID][] = $row;
                } else {
                    $this->whitelist[$planID] = array($row);
                }
            }
        }

        // Separator

        $query = get_sql_query(
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
        if (!empty($query)) {
            foreach ($query as $row) {
                $planID = $row->plan_id ?? 0;

                if (array_key_exists($planID, $this->blacklist)) {
                    $this->blacklist[$planID][] = $row;
                } else {
                    $this->blacklist[$planID] = array($row);
                }
            }
        }
    }

    // Separator

    public function getList(?DiscordPlan $plan = null): array
    {
        return array_merge($this->list[$plan->planID ?? 0] ?? array(), $this->temporary);
    }

    public function getWhitelist(DiscordPlan $plan = null): array
    {
        return $this->whitelist[$plan->planID ?? 0] ?? array();
    }

    public function isBlacklisted(?DiscordPlan $plan, object $channel): bool
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

        if ($plan === null) {
            foreach ($this->blacklist as $row) {
                foreach ($row as $rowChannel) {
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
            }
        } else if (array_key_exists($plan->planID, $this->blacklist)) {
            foreach ($this->blacklist[$plan->planID] as $row) {
                if (($guildID === null
                        || $row->server_id == $guildID)
                    && ($row->category_id === null
                        || $row->category_id == $parentID)
                    && ($row->channel_id === null
                        || $row->channel_id == $channelID)
                    && ($row->thread_id === null
                        || $row->thread_id == $threadID)) {
                    return true;
                }
            }
        }
        return false;
    }

    public function getIfHasAccess(?DiscordPlan $plan, Channel|Thread $channel, Member|User $member): ?object
    {
        $cacheKey = array(
            __METHOD__,
            $plan->planID ?? 0,
            $channel->guild_id,
            $channel->id,
            $member->id,
        );
        $cache = get_key_value_pair($cacheKey);

        if ($cache !== null) {
            return $cache === false ? null : $cache;
        } else {
            $result = false;
            $list = $this->getList($plan);

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
            set_key_value_pair($cacheKey, $result);
            return $result === false ? null : $result;
        }
    }

    public function addTemporary(?DiscordPlan $plan, Channel $channel, ?array $properties = null): bool
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
            $object->plan_id = $plan?->planID;
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

    public static function getThreadHistory(Channel    $channel,
                                            array      &$array,
                                            int|string $maxThreads,
                                            int|string $maxMessagesPerThread): array
    {
        if (!empty($channel->threads)) {
            $updated = array();

            foreach ($channel->threads as $thread) {
                $thread->getMessageHistory(
                    [
                        'limit' => (int)$maxMessagesPerThread,
                        'cache' => true
                    ]
                )->done(function ($channel, $thread, $messageHistory, $maxMessagesPerThread, &$updated, &$array) {
                    foreach ($messageHistory as $message) {
                        if (array_key_exists($channel->id, $array)) {
                            if (array_key_exists($thread->id, $array[$channel->id])) {
                                if (sizeof($array[$channel->id][$thread->id]) == $maxMessagesPerThread) {
                                    array_shift($array[$channel->id][$thread->id]);
                                }
                                $array[$channel->id][$thread->id][] = $message;
                            } else {
                                $array[$channel->id][$thread->id] = array($message);
                            }
                        } else {
                            $array[$channel->id][$thread->id] = array($message);
                        }
                        $updated[] = $thread->id;
                    }
                });

                if (sizeof($updated) == $maxThreads) {
                    break;
                }
            }
            $totalThreads = sizeof($array[$channel->id]);

            if ($totalThreads > $maxThreads) {
                $totalThreads -= $maxThreads;

                foreach (array_keys($array[$channel->id]) as $threadID) {
                    if (!in_array($threadID, $updated)) {
                        unset($array[$channel->id][$threadID]);
                        $totalThreads--;

                        if ($totalThreads == 0) {
                            break;
                        }
                    }
                }
            }
        }
        return $array;
    }

}