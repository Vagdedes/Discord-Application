<?php

use Discord\Parts\Channel\Channel;
use Discord\Parts\Channel\Message;
use Discord\Parts\Guild\Guild;
use Discord\Parts\Thread\Thread;
use Discord\Parts\User\Member;
use Discord\Parts\User\User;

class DiscordInstructions
{

    private DiscordPlan $plan;
    public mixed $manager;

    public function __construct(DiscordPlan $plan)
    {
        $account = new Account();
        $this->plan = $plan;
        $this->manager = $account->getInstructions();
        $this->manager->setAI($plan->aiMessages->getManagerAI());
    }

    public function replace(array   $messages,
                            ?object $object,
                            ?array  $specificPublic = null,
                            ?string $userInput = null,
                            bool    $callables = false,
                            bool    $extra = false): array
    {
        if ($object !== null) {
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
                        return $this->plan->aiMessages->getReplies(
                            $object->serverID,
                            $object->channelID,
                            $object->threadID,
                            $object->userID,
                            $object->messageHistory,
                            DiscordAIMessages::PAST_MESSAGES
                        );
                    },
                    "botMessages" => function () use ($object) {
                        return $this->plan->aiMessages->getMessages(
                            $object->serverID,
                            $object->channelID,
                            $object->threadID,
                            $object->userID,
                            $object->messageHistory,
                            DiscordAIMessages::PAST_MESSAGES
                        );
                    },
                    "allMessages" => function () use ($object) {
                        return $this->plan->aiMessages->getConversation(
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
                            $channel = $this->plan->bot->discord->getChannel($object->channelID);

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
        } else {
            return $messages;
        }
    }

    public function build(object  $object,
                          ?array  $specificLocal = null,
                          ?array  $specificPublic = null,
                          ?string $userInput = null): string
    {
        $local = $this->manager->getLocal($specificLocal, $userInput);

        if (!empty($local)) {
            $information = "";

            foreach ($local as $instruction) {
                $information .= $this->replace(
                    array($instruction),
                    $object,
                    $specificPublic,
                    $userInput,
                    true,
                    true
                )[0];
            }
            return $information;
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
        $object->botID = $this->plan->bot->botID;
        $object->botName = $this->plan->bot->discord->user->id;
        $object->domain = get_domain();
        $object->date = get_current_date();
        $object->year = date("Y");
        $object->month = date("m");
        $object->hour = date("H");
        $object->minute = date("i");
        $object->second = date("s");
        $object->channel = $channel === null || $user === null ? null
            : $this->plan->bot->channels->getIfHasAccess($this->plan, $channel, $user);

        $object->placeholderArray = array();
        $object->newLine = DiscordProperties::NEW_LINE;

        $object->planName = $this->plan->name;
        $object->planDescription = $this->plan->description;
        $object->planCreationDate = $this->plan->creationDate;
        $object->planCreationReason = $this->plan->creationReason;
        $object->planExpirationDate = $this->plan->expirationDate;
        $object->planExpirationReason = $this->plan->expirationReason;
        return $object;
    }
}