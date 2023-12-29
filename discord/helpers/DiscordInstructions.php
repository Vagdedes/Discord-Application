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
    private mixed $instructions;

    public const
        DEFAULT_PLACEHOLDER_START = "%%__",
        DEFAULT_PLACEHOLDER_MIDDLE = "__",
        DEFAULT_PLACEHOLDER_END = "__%%";

    public function __construct(DiscordPlan $plan)
    {
        $this->plan = $plan;
        $this->instructions = $plan->bot->getAccount()->getInstructions();
    }

    public function replace(array  $messages, ?object $object,
                            string $placeholderStart = self::DEFAULT_PLACEHOLDER_START,
                            string $placeholderMiddle = self::DEFAULT_PLACEHOLDER_MIDDLE,
                            string $placeholderEnd = self::DEFAULT_PLACEHOLDER_END,
                            bool   $recursive = true): array
    {
        if ($object !== null) {
            $this->instructions->setPlaceholderStart($placeholderStart);
            $this->instructions->setPlaceholderMiddle($placeholderMiddle);
            $this->instructions->setPlaceholderEnd($placeholderEnd);
            return $this->instructions->replace(
                $messages,
                $object,
                array(
                    "publicInstructions" => array($this->instructions, "getPublic", array()),
                    "botReplies" => array($this->plan->aiMessages, "getReplies", array($object->userID, new stdClass(), false)),
                    "botMessages" => array($this->plan->aiMessages, "getMessages", array($object->userID, new stdClass(), false)),
                    "allMessages" => array($this->plan->aiMessages, "getConversation", array($object->userID, new stdClass(), false))
                ),
                $recursive
            );
        } else {
            return $messages;
        }
    }

    public function build(object $object, ?array $specific = null): array
    {
        if (!empty($this->instructions->getLocal())) {
            $information = "";
            $disclaimer = "";
            $hasSpecific = $specific !== null;

            foreach ($this->instructions->getLocal() as $instruction) {
                if (!$hasSpecific || in_array($instruction->id, $specific)) {
                    $replacements = $this->replace(
                        array(
                            $instruction->information,
                            $instruction->disclaimer
                        ),
                        $object,
                        $instruction->placeholder_start,
                        $instruction->placeholder_middle,
                        $instruction->placeholder_end
                    );
                    $information .= $replacements[0];
                    $disclaimer .= $replacements[1];
                }
            }
            if ($object->channel !== null
                && $object->channel->strict_reply !== null) {
                $information = ($object->channel->require_mention
                        ? DiscordProperties::STRICT_REPLY_INSTRUCTIONS_WITH_MENTION
                        : DiscordProperties::STRICT_REPLY_INSTRUCTIONS)
                    . DiscordProperties::NEW_LINE . DiscordProperties::NEW_LINE . $information;
            }
            if (!empty($information)) {
                return array(
                    $information,
                    (!empty($disclaimer)
                        ? DiscordProperties::NEW_LINE . DiscordSyntax::SPOILER . $disclaimer . DiscordSyntax::SPOILER
                        : "")
                );
            }
        }
        return array("", "");
    }

    public function getObject(?Guild              $server = null,
                              Channel|Thread|null $channel = null,
                              ?Thread             $thread = null,
                              Member|User|null    $user = null,
                              ?Message            $message = null): object
    {
        $object = new stdClass();
        $object->serverID = $server?->id;
        $object->serverName = $server?->name;
        $object->channelID = $channel instanceof Thread
            ? $channel->parent?->id
            : $channel?->id;
        $object->channelName = $channel instanceof Thread
            ? $channel->parent?->name
            : $channel?->name;
        $object->threadID = $thread?->id;
        $object->threadName = $thread?->name;
        $object->userID = $user?->id;
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
        $object->channel = $object->serverID === null || $object->channelID === null || $object->userID === null ? null
            : $this->plan->channels->getIfHasAccess($object->serverID, $object->channelID, $object->userID);

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