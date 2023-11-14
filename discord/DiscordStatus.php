<?php
class DiscordStatus
{
    private DiscordPlan $plan;

    public function __construct(DiscordPlan $plan)
    {
        $this->plan = $plan;
    }

    public function welcome(int|string $serverID, int|string $userID): void
    {
        $this->plan->bot->processing++;

        if (!has_memory_limit(
                array(__METHOD__, $this->plan->planID, $serverID, $userID),
                1
            )
            && !empty($this->plan->locations->channels)) {
            foreach ($this->plan->locations->channels as $channel) {
                if ($channel->server_id == $serverID
                    && $channel->welcome_message !== null) {
                    if ($channel->whitelist === null) {
                        $channelFound = $this->plan->discord->getChannel($channel->channel_id);

                        if ($channelFound !== null
                            && $channelFound->allowText()) {
                            $channelFound->sendMessage("<@$userID> " . $channel->welcome_message);
                        }
                    } else if (!empty($this->plan->locations->whitelistContents)) {
                        foreach ($this->plan->locations->whitelistContents as $whitelist) {
                            if ($whitelist->user_id == $userID
                                && ($whitelist->server_id === null
                                    || $whitelist->server_id === $serverID
                                    && ($whitelist->channel_id === null
                                        || $whitelist->channel_id === $channel->channel_id))) {
                                $channelFound = $this->plan->discord->getChannel($channel->channel_id);

                                if ($channelFound !== null
                                    && $channelFound->allowText()) {
                                    $channelFound->sendMessage("<@$userID> " . $channel->welcome_message);
                                }
                                break;
                            }
                        }
                    }
                }
            }
        }
        $this->plan->bot->processing--;
    }

    public function goodbye(int|string $serverID, int|string $userID): void
    {

    }
}