<?php

use Discord\Parts\Channel\Channel;

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

    public function getIfHasAccess(int|string $serverID, int|string $channelID, int|string $userID): ?object
    {
        $cacheKey = array(__METHOD__, $this->plan->planID, $serverID, $channelID, $userID);
        $cache = get_key_value_pair($cacheKey);

        if ($cache !== null) {
            return $cache === false ? null : $cache;
        } else {
            $result = false;
            $list = $this->getList();

            if (!empty($list)) {
                foreach ($list as $channel) {
                    if ($channel->server_id == $serverID
                        && ($channel->channel_id == $channelID
                            || $channel->channel_id === null)) {
                        if ($channel->whitelist === null) {
                            $result = $channel;
                            break;
                        } else if (!empty($this->whitelist)) {
                            foreach ($this->whitelist as $whitelist) {
                                if ($whitelist->user_id == $userID
                                    && ($whitelist->server_id === null
                                        || $whitelist->server_id === $serverID
                                        && ($whitelist->channel_id === null
                                            || $whitelist->channel_id === $channelID))) {
                                    $result = $channel;
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
            $object->channel_id = $channel->id;
            $object->whitelist = null;
            $object->debug = null;
            $object->require_mention = null;
            $object->strict_reply = null;
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