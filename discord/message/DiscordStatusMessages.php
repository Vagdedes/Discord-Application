<?php

class DiscordStatusMessages
{
    private DiscordPlan $plan;

    private const
        WELCOME = 1,
        GOODBYE = 2;

    public function __construct(DiscordPlan $plan)
    {
        $this->plan = $plan;
    }

    public function welcome(int|string $serverID, int|string $userID): void
    {
        if (!$this->hasCooldown($serverID, $userID, self::WELCOME)
            && !empty($this->plan->locations->channels)) {
            foreach ($this->plan->locations->channels as $channel) {
                if ($channel->server_id == $serverID
                    && $channel->welcome_message !== null) {
                    if ($channel->whitelist === null) {
                        $channelFound = $this->plan->discord->getChannel($channel->channel_id);

                        if ($channelFound !== null
                            && $channelFound->allowText()) {
                            $this->addCooldown($serverID, $userID, self::WELCOME);
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
                                    $this->addCooldown($serverID, $userID, self::WELCOME);
                                    $channelFound->sendMessage("<@$userID> " . $channel->welcome_message);
                                }
                                break;
                            }
                        }
                    }
                }
            }
        }
    }

    public function goodbye(int|string $serverID, int|string $userID): void
    {
        if (!$this->hasCooldown($serverID, $userID, self::GOODBYE)
            && !empty($this->plan->locations->channels)) {
            foreach ($this->plan->locations->channels as $channel) {
                if ($channel->server_id == $serverID
                    && $channel->goodbye_message !== null) {
                    if ($channel->whitelist === null) {
                        $channelFound = $this->plan->discord->getChannel($channel->channel_id);

                        if ($channelFound !== null
                            && $channelFound->allowText()) {
                            $this->addCooldown($serverID, $userID, self::GOODBYE);
                            $channelFound->sendMessage(
                                $this->plan->utilities->getUsername($userID)
                                . " " . $channel->goodbye_message
                            );
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
                                    $this->addCooldown($serverID, $userID, self::GOODBYE);
                                    $channelFound->sendMessage(
                                        $this->plan->utilities->getUsername($userID)
                                        . " " . $channel->goodbye_message
                                    );
                                }
                                break;
                            }
                        }
                    }
                }
            }
        }
    }

    private function hasCooldown(int|string $serverID, int|string $userID, int $case): bool
    {
        return !has_memory_cooldown(
            array(__METHOD__, $serverID, $userID, $case),
            "5 minutes", false);
    }

    private function addCooldown(int|string $serverID, int|string $userID, int $case): void
    {
        has_memory_cooldown(
            array(__METHOD__, $serverID, $userID, $case),
            "5 minutes", true, true);
    }
}