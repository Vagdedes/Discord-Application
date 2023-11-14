<?php

use Discord\Builders\MessageBuilder;
use Discord\Parts\Channel\Message;
use Discord\Parts\User\Member;
use Discord\Parts\User\User;

class DiscordLocations
{
    private DiscordPlan $plan;
    public array $channels, $whitelistContents, $keywords, $mentions;

    public function __construct(DiscordPlan $plan)
    {
        $this->plan = $plan;
        $this->keywords = get_sql_query(
            BotDatabaseTable::BOT_KEYWORDS,
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
        $this->channels = get_sql_query(
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
        if (!empty($this->channels)) {
            $this->mentions = array();

            foreach ($this->channels as $channel) {
                if ($channel->require_mention !== null) {
                    $this->mentions = get_sql_query(
                        BotDatabaseTable::BOT_MENTIONS,
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
                    break;
                }
            }
        } else {
            $this->mentions = array();
        }
        $this->whitelistContents = get_sql_query(
            BotDatabaseTable::BOT_WHITELIST,
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

    public function getChannel(int|string $serverID, int|string $channelID, int|string $userID): ?object
    {
        $cacheKey = array(__METHOD__, $this->plan->planID, $serverID, $channelID, $userID);
        $cache = get_key_value_pair($cacheKey);

        if ($cache !== null) {
            return $cache === false ? null : $cache;
        } else {
            $result = false;

            if (!empty($this->channels)) {
                foreach ($this->channels as $channel) {
                    if ($channel->server_id == $serverID
                        && ($channel->channel_id == $channelID
                            || $channel->channel_id === null)) {
                        if ($channel->whitelist === null) {
                            $result = $channel;
                            break;
                        } else if (!empty($this->whitelistContents)) {
                            foreach ($this->whitelistContents as $whitelist) {
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
}