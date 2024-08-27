<?php

use Discord\Builders\MessageBuilder;
use Discord\Parts\Channel\Channel;
use Discord\Parts\Thread\Thread;
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

        if (!$this->hasCooldown($member, $type)) {
            $list = $this->plan->bot->channels->getList($this->plan);

            if (!empty($list)) {
                $messageColumn = $type === self::WELCOME ? "welcome_message" : "goodbye_message";

                foreach ($list as $channel) {
                    if ($channel->server_id == $serverID
                        && $channel->{$messageColumn} !== null) {
                        if ($channel->whitelist === null) {
                            $channelFound = $this->plan->bot->discord->getChannel($channel->channel_id);

                            if ($channel->thread_id !== null) {
                                if (!empty($channelFound->threads->first())) {
                                    foreach ($channelFound->threads as $thread) {
                                        if ($thread instanceof Thread && $channel->thread_id == $thread->id) {
                                            $channelFound = $thread;
                                            break;
                                        }
                                    }
                                }
                            }

                            if ($channelFound !== null
                                && $channelFound->allowText()
                                && $channelFound->guild_id == $serverID) {
                                $this->process($channelFound, $member, $channel, $channel->{$messageColumn}, $type);
                            }
                        } else {
                            $whitelist = $this->plan->bot->channels->getWhitelist($this->plan);

                            if (!empty($whitelist)) {
                                foreach ($whitelist as $whitelisted) {
                                    if ($whitelisted->user_id == $userID
                                        && ($whitelisted->server_id === null
                                            || $whitelisted->server_id == $serverID
                                            && ($whitelisted->channel_id === null || $whitelisted->channel_id == $channel->channel_id)
                                            && ($whitelisted->thread_id === null || $whitelisted->thread_id == $channel->thread_id))) {
                                        $channelFound = $this->plan->bot->discord->getChannel($channel->channel_id);

                                        if ($channel->thread_id !== null) {
                                            if (!empty($channelFound->threads->first())) {
                                                foreach ($channelFound->threads as $thread) {
                                                    if ($thread instanceof Thread && $channel->thread_id == $thread->id) {
                                                        $channelFound = $thread;
                                                        break;
                                                    }
                                                }
                                            }
                                        }

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
        }
    }

    private function process(Channel|Thread $channelFound, Member $member,
                             object         $channel,
                             ?string        $message,
                             int            $case): void
    {
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
            array(
                self::class,
                $this->plan->planID,
                $member->guild_id,
                $member->id,
                $case
            ),
            "5 minutes"
        );
    }
}