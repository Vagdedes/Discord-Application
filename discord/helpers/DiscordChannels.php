<?php

use Discord\Parts\Channel\Channel;
use Discord\Parts\Thread\Thread;
use Discord\Parts\User\Member;
use Discord\Parts\User\User;

class DiscordChannels
{
    private DiscordPlan $plan;
    private array $list, $whitelist, $temporary;

    public function __construct(DiscordPlan $plan)
    {
        $this->plan = $plan;
        $this->temporary = array();
        $this->list = get_sql_query(
            BotDatabaseTable::BOT_CHANNELS,
            null,
            array(
                array("deletion_date", null),
                array("plan_id", $this->plan->planID),
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
        $this->whitelist = get_sql_query(
            BotDatabaseTable::BOT_CHANNEL_WHITELIST,
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

    // Separator

    public function getList(): array
    {
        return array_merge($this->list, $this->temporary);
    }

    public function getWhitelist(): array
    {
        return $this->whitelist;
    }

    public function getIfHasAccess(Channel|Thread $channel, Member|User $member): ?object
    {
        $cacheKey = array(
            __METHOD__,
            $this->plan->planID,
            $channel->guild_id,
            $channel->id,
            $member->id,
        );
        $cache = get_key_value_pair($cacheKey);

        if ($cache !== null) {
            return $cache === false ? null : $cache;
        } else {
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
            set_key_value_pair($cacheKey, $result);
            return $result === false ? null : $result;
        }
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
            $object->plan_id = $this->plan->planID;
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
}