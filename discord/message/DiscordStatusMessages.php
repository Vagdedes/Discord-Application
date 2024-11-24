<?php

use Discord\Builders\MessageBuilder;
use Discord\Parts\Channel\Channel;
use Discord\Parts\Thread\Thread;
use Discord\Parts\User\Member;

class DiscordStatusMessages
{
    private DiscordBot $bot;

    public const
        WELCOME = 1,
        GOODBYE = 2;

    public function __construct(DiscordBot $bot)
    {
        $this->bot = $bot;
    }

    public function run(Member $member, int $type): void
    {
        if (!$this->hasCooldown($member, $type)) {
            $list = $this->bot->channels->getList();

            if (!empty($list)) {
                $serverID = $member->guild_id;
                $messageColumn = $type === self::WELCOME ? "welcome_message" : "goodbye_message";

                foreach ($list as $channel) {
                    if ($channel->server_id == $serverID
                        && $channel->{$messageColumn} !== null) {
                        if ($channel->whitelist === null) {
                            $channelFound = $this->bot->discord->getChannel($channel->channel_id);

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
                                && $this->bot->utilities->allowText($channelFound)
                                && $channelFound->guild_id == $serverID) {
                                $this->process($channelFound, $member, $channel, $channel->{$messageColumn}, $type);
                            }
                        } else {
                            $whitelist = $this->bot->channels->getWhitelist();

                            if (!empty($whitelist)) {
                                $userID = $member->id;

                                foreach ($whitelist as $whitelisted) {
                                    if ($whitelisted->user_id == $userID
                                        && ($whitelisted->server_id === null
                                            || $whitelisted->server_id == $serverID
                                            && ($whitelisted->channel_id === null || $whitelisted->channel_id == $channel->channel_id)
                                            && ($whitelisted->thread_id === null || $whitelisted->thread_id == $channel->thread_id))) {
                                        $channelFound = $this->bot->discord->getChannel($channel->channel_id);

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
                                            && $this->bot->utilities->allowText($channelFound)
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
            $this->bot->listener->callStatusMessageImplementation(
                $channel->listener_class,
                $channel->listener_method,
                $channelFound,
                $member,
                MessageBuilder::new()->setContent(
                    $this->bot->instructions->replace(array($message),
                        $this->bot->instructions->getObject(
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
                $this->bot->botID,
                $member->guild_id,
                $member->id,
                $case
            ),
            "1 minute"
        );
    }

}