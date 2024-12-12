<?php

use Discord\Parts\Channel\Channel;
use Discord\Parts\Channel\Message;
use Discord\Parts\Guild\Guild;
use Discord\Parts\Thread\Thread;
use Discord\Parts\User\Member;
use Discord\Parts\User\User;

class DiscordInstructions
{

    private DiscordBot $bot;
    private array $managers;

    public function __construct(DiscordBot $bot)
    {
        $this->bot = $bot;
        $this->managers = array();
    }

    public function get(int|string|Guild $serverID, mixed $managerAI = null): mixed
    {
        if ($serverID instanceof Guild) {
            $serverID = $serverID->id;
        }
        if (!array_key_exists($serverID, $this->managers)) {
            $account = new Account();
            $this->managers[$serverID] = $account->getInstructions();
        }
        if ($managerAI !== null) {
            $this->managers[$serverID]->setAI($managerAI);
        }
        return $this->managers[$serverID];
    }

    public function replace(array   $messages,
                            ?object $object,
                            ?array  $specificPublic = null,
                            ?string $userInput = null,
                            bool    $callables = false,
                            bool    $extra = false,): array
    {
        if ($object === null) {
            $account = new Account();
            $manager = $account->getInstructions();
        } else {
            $manager = $this->get($object->serverID);
        }
        return $manager->replace(
            $messages,
            $object,
            !$callables
                ? null
                : array(
                "publicInstructions" => function () use ($manager, $specificPublic, $userInput) {
                    return $manager->getPublic(
                        $specificPublic,
                        $userInput,
                        false
                    );
                },
                "botReplies" => function () use ($object) {
                    return $this->bot->aiMessages->getReplies(
                        $object->serverID,
                        $object->channelID,
                        $object->threadID,
                        $object->userID,
                        $object->messageHistory,
                        DiscordAIMessages::PAST_MESSAGES_COUNT,
                        DiscordAIMessages::PAST_MESSAGES_LENGTH
                    );
                },
                "threadMessages" => function () use ($object) {
                    if ($object->channelID !== null) {
                        $channel = $this->bot->discord->getChannel($object->channelID);

                        if ($channel !== null) {
                            return DiscordChannels::getAsyncThreadHistory(
                                $channel,
                                DiscordAIMessages::THREADS_ANALYZED,
                                DiscordAIMessages::THREAD_ANALYZED_MESSAGES,
                                DiscordAIMessages::PAST_MESSAGES_LENGTH
                            );
                        } else {
                            return array();
                        }
                    } else {
                        return array();
                    }
                }
            ),
            $extra
        );
    }

    public function getObject(?Guild              $server = null,
                              Channel|Thread|null $channel = null,
                              Member|User|null    $user = null,
                              ?Message            $message = null,
                              mixed               $messageHistory = []): object
    {
        $object = new stdClass();
        $object->messageHistory = $messageHistory;

        if ($server === null) {
            if ($channel === null) {
                if ($user instanceof Member) {
                    $object->serverID = $user->guild_id;
                    $object->serverName = $user->guild->name;
                } else {
                    $object->serverID = null;
                    $object->serverName = null;
                }
            } else {
                $object->serverID = $channel->guild_id;
                $object->serverName = $channel->guild->name;
            }
        } else {
            $object->serverID = $server?->id;
            $object->serverName = $server?->name;
        }
        if ($channel instanceof Thread) {
            $object->channelID = $channel->parent->id;
            $object->channelName = $channel->parent->name;
            $object->threadID = $channel->id;
            $object->threadName = $channel->name;
        } else {
            $object->channelID = $channel?->id;
            $object->channelName = $channel?->name;
            $object->threadID = null;
            $object->threadName = null;
        }
        $object->userID = $user?->id;
        $object->userTag = "<@" . $object->userID . ">";
        $object->userName = $user?->username;
        $object->displayName = $user?->displayname;
        $object->messageContent = $message?->content;
        $object->messageID = $message?->id;
        $object->botID = $this->bot->botID;
        $object->botName = $this->bot->discord->user->id;
        $object->domain = get_domain();
        $object->date = get_current_date();
        $object->year = date("Y");
        $object->month = date("m");
        $object->hour = date("H");
        $object->minute = date("i");
        $object->second = date("s");
        $object->channel = $channel === null || $user === null ? null
            : $this->bot->channels->getIfHasAccess($channel, $user);

        $object->placeholderArray = array();
        $object->newLine = DiscordProperties::NEW_LINE;
        return $object;
    }

}