<?php

use Discord\Builders\MessageBuilder;
use Discord\Parts\Channel\Channel;
use Discord\Parts\User\Member;

class DiscordStatusMessages
{
    private DiscordPlan $plan;

    public const
        WELCOME = 1,
        GOODBYE = 2;

    public function __construct(DiscordPlan $plan)
    {
        $this->plan = $plan;
    }

    public function run(Member $member, int $type): void
    {
        $serverID = $member->guild_id;
        $userID = $member->id;

        if (!$this->hasCooldown($member, $type)
            && !empty($this->plan->channels->getList())) {
            $messageColumn = $type === self::WELCOME ? "welcome_message" : "goodbye_message";

            foreach ($this->plan->channels->getList() as $channel) {
                if ($channel->server_id == $serverID
                    && $channel->{$messageColumn} !== null) {
                    if ($channel->whitelist === null) {
                        $channelFound = $this->plan->bot->discord->getChannel($channel->channel_id);

                        if ($channelFound !== null
                            && $channelFound->allowText()
                            && $channelFound->guild_id == $serverID) {
                            $this->process($channelFound, $member, $channel, $channel->{$messageColumn}, $type);
                        }
                    } else if (!empty($this->plan->channels->getWhitelist())) {
                        foreach ($this->plan->channels->getWhitelist() as $whitelist) {
                            if ($whitelist->user_id == $userID
                                && ($whitelist->server_id === null
                                    || $whitelist->server_id == $serverID
                                    && ($whitelist->channel_id === null || $whitelist->channel_id == $channel->channel_id))) {
                                $channelFound = $this->plan->bot->discord->getChannel($channel->channel_id);

                                if ($channelFound !== null
                                    && $channelFound->allowText()
                                    && $channelFound->guild_id == $serverID) {
                                    $this->process($channelFound, $member, $channel, $channel->{$messageColumn}, $type);
                                }
                                break;
                            }
                        }
                    }
                }
            }
        }
    }

    private function process(Channel $channelFound, Member $member,
                             object  $channel,
                             ?string $message,
                             int     $case): void
    {
        has_memory_cooldown(
            array(self::class, $this->plan->planID, $member->guild_id, $member->id, $case),
            "5 minutes",
            true,
            true
        );
        if ($message === null) {
            // todo ai
        }
        $channelFound->sendMessage(
            $this->plan->listener->callStatusMessageImplementation(
                $channel->listener_class,
                $channel->listener_method,
                $channelFound,
                $member,
                MessageBuilder::new()->setContent(
                    $this->plan->instructions->replace(array($message),
                        $this->plan->instructions->getObject(
                            $member->guild,
                            $channelFound,
                            $member,
                        )
                    )[0]
                ),
                $channel,
                $case
            )
        );
    }

    private function hasCooldown(Member $member, int $case): bool
    {
        return has_memory_cooldown(
            array(self::class, $member->guild_id, $member->id, $case),
            "5 minutes", false);
    }
}