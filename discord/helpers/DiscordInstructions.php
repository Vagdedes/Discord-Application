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
    public mixed $manager;

    public function __construct(DiscordBot $bot)
    {
        $account = new Account();
        $this->bot = $bot;
        $this->manager = $account->getInstructions();
        $this->manager->setAI($bot->aiMessages->getManagerAI());
    }

    public function replace(array   $messages,
                            ?object $object,
                            ?array  $specificPublic = null,
                            ?string $userInput = null,
                            bool    $callables = false,
                            bool    $extra = false): array
    {
        return $this->manager->replace(
            $messages,
            $object,
            !$callables
                ? null
                : array(
                "publicInstructions" => function () use ($specificPublic, $userInput) {
                    return $this->manager->getPublic(
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
                        DiscordAIMessages::PAST_MESSAGES
                    );
                },
                "botMessages" => function () use ($object) {
                    return $this->bot->aiMessages->getMessages(
                        $object->serverID,
                        $object->channelID,
                        $object->threadID,
                        $object->userID,
                        $object->messageHistory,
                        DiscordAIMessages::PAST_MESSAGES
                    );
                },
                "allMessages" => function () use ($object) {
                    return $this->bot->aiMessages->getConversation(
                        $object->serverID,
                        $object->channelID,
                        $object->threadID,
                        $object->userID,
                        $object->messageHistory,
                        DiscordAIMessages::PAST_MESSAGES
                    );
                },
                "threadMessages" => function () use ($object) {
                    if ($object->channelID !== null) {
                        $channel = $this->bot->discord->getChannel($object->channelID);

                        if ($channel !== null) {
                            return DiscordChannels::getAsyncThreadHistory(
                                $channel,
                                DiscordAIMessages::THREADS_ANALYZED,
                                DiscordAIMessages::THREAD_ANALYZED_MESSAGES
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

    public function build(object  $object,
                          ?array  $specificLocal = null,
                          ?array  $specificPublic = null,
                          ?string $userInput = null): string
    {
        $local = $this->manager->getLocal($specificLocal, $userInput);

        if (!empty($local)) {
            foreach ($local as $key => $value) {
                $local[$key] = $this->replace(
                    array($value),
                    $object,
                    $specificPublic,
                    $userInput,
                    true,
                    true
                )[0];
            }
            return @json_encode($local);
        } else {
            return "";
        }
    }

    public function getObject(?Guild              $server = null,
                              Channel|Thread|null $channel = null,
                              Member|User|null    $user = null,
                              ?Message            $message = null,
                              mixed               $messageHistory = []): object
    {
        $object = new stdClass();
        $object->messageHistory = $messageHistory;
        $object->serverID = $server?->id;
        $object->serverName = $server?->name;

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