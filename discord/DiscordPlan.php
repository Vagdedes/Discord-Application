<?php

use Discord\Builders\MessageBuilder;
use Discord\Discord;
use Discord\Parts\Channel\Message;
use Discord\Parts\User\Member;
use Discord\Parts\User\User;

class DiscordPlan
{
    public int $planID, $botID;
    public ?int $applicationID, $family;
    private bool $debug;
    public string $name, $creationDate;
    public ?string $description, $expirationDate, $creationReason, $expirationReason;
    private array $channels, $whitelistContents, $keywords, $mentions;
    private ?ChatAI $chatAI;
    public Discord $discord;
    public DiscordInstructions $instructions;
    public DiscordConversation $conversation;
    public DiscordModeration $moderation;
    public DiscordLimits $limits;
    public DiscordCommands $commands;
    public DiscordListener $listener;
    public DiscordComponent $component;
    public DiscordControlledMessages $controlledMessages;
    public DiscordTicket $ticket;
    public DiscordMessageRefresh $messageRefresh;
    public DiscordPermissions $permissions;
    public DiscordUtilities $utilities;
    private DiscordBot $bot;
    private DiscordGoal $goal;

    public function __construct(Discord $discord,
                                DiscordBot $bot,
                                int|string $botID, int|string $planID)
    {
        $query = get_sql_query(
            BotDatabaseTable::BOT_PLANS,
            null,
            array(
                array("id", $planID),
            ),
            null,
            1
        );
        $query = $query[0];

        $this->discord = $discord;
        $this->botID = $botID;
        $this->planID = (int)$query->id;
        $this->family = $query->family === null ? null : (int)$query->family;
        $this->applicationID = $query->application_id === null ? null : (int)$query->application_id;
        $this->name = $query->name;
        $this->description = $query->description;
        $this->creationDate = $query->creation_date;
        $this->creationReason = $query->creation_reason;
        $this->expirationDate = $query->expiration_date;
        $this->expirationReason = $query->expiration_reason;
        $this->debug = false;

        $this->bot = $bot;
        $this->instructions = new DiscordInstructions($this);
        $this->conversation = new DiscordConversation($this);
        $this->moderation = new DiscordModeration($this);
        $this->limits = new DiscordLimits($this);
        $this->commands = new DiscordCommands($this);
        $this->listener = new DiscordListener($this);
        $this->component = new DiscordComponent($this);
        $this->controlledMessages = new DiscordControlledMessages($this);
        $this->ticket = new DiscordTicket($this);
        $this->messageRefresh = new DiscordMessageRefresh($this);
        $this->permissions = new DiscordPermissions($this);
        $this->utilities = new DiscordUtilities($this);
        $this->goal = new DiscordGoal($this);

        $this->keywords = get_sql_query(
            BotDatabaseTable::BOT_KEYWORDS,
            null,
            array(
                array("deletion_date", null),
                null,
                array("plan_id", "IS", null, 0),
                array("plan_id", $this->planID),
                null,
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
                null,
                array("plan_id", "IS", null, 0),
                array("plan_id", $this->planID),
                null,
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
                            null,
                            array("plan_id", "IS", null, 0),
                            array("plan_id", $this->planID),
                            null,
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
                array("plan_id", $this->planID),
                null,
                null,
                array("expiration_date", "IS", null, 0),
                array("expiration_date", ">", get_current_date()),
                null
            )
        );
        $query = get_sql_query(
            BotDatabaseTable::BOT_CHAT_MODEL,
            null,
            array(
                array("plan_id", $this->planID),
                array("deletion_date", null)
            ),
            null,
            1
        );

        if (!empty($query)) {
            global $AI_key;
            $query = $query[0];
            $this->chatAI = new ChatAI(
                $query->model_family,
                $AI_key[0],
                DiscordProperties::MESSAGE_MAX_LENGTH,
                $query->temperature,
                $query->frequency_penalty,
                $query->presence_penalty,
                $query->completions,
                $query->top_p,
            );
        } else {
            $this->chatAI = null;
        }
    }

    // Separator

    public function getChannel(int|string $serverID, int|string $channelID, int|string $userID): ?object
    {
        $cacheKey = array(__METHOD__, $this->planID, $serverID, $channelID, $userID);
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
                        $this->debug = $channel->debug !== null;

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

    public function assist(Message         $message,
                           User            $user,
                           Member          $member,
                           string          $serverName,
                           string          $channelName,
                           int|string|null $threadID, string|null $threadName,
                           string          $messageContent): bool
    {
        $this->bot->processing++;
        global $logger;
        $punishment = $this->moderation->hasPunishment(DiscordPunishment::CUSTOM_BLACKLIST, $user->id);
        $object = $this->instructions->getObject(
            $message->guild_id,
            $serverName,
            $message->channel_id,
            $channelName,
            $threadID,
            $threadName,
            $user->id,
            $user->username,
            $user->displayname,
            $messageContent,
            $message->id,
            $this->discord->user->displayname
        );
        $command = $this->commands->process(
            $message,
            $member
        );

        if ($command !== null) {
            if ($punishment !== null) {
                if ($punishment->notify !== null) {
                    $message->reply($this->instructions->replace(array($punishment->creation_reason), $object)[0]);
                }
            } else if ($command instanceof MessageBuilder) {
                $message->reply($command);
            } else {
                $message->reply($this->instructions->replace(array($command), $object)[0]);
            }
            $this->bot->processing--;
            return true;
        } else {
            $channel = $this->getChannel($message->guild_id, $message->channel_id, $user->id);

            if ($channel !== null) {
                if ($this->chatAI !== null
                    && $this->chatAI->exists) {
                    if ($punishment !== null) {
                        if ($punishment->notify !== null) {
                            $message->reply($this->instructions->replace(array($punishment->creation_reason), $object)[0]);
                        }
                    } else {
                        $cooldownKey = array(__METHOD__, $this->planID, $user->id);

                        if (get_key_value_pair($cooldownKey) === null) {
                            set_key_value_pair($cooldownKey, true);
                            if ($user->id != $this->botID) {
                                if ($channel->require_mention) {
                                    $mention = false;

                                    if (!empty($message->mentions->getIterator())) {
                                        foreach ($message->mentions as $userObj) {
                                            if ($userObj->id == $this->botID) {
                                                $mention = true;
                                                break;
                                            }
                                        }

                                        if ($mention) {
                                            $messageContent = str_replace("<@" . $this->botID . ">", "", $messageContent);
                                        } else if (!empty($this->mentions)) {
                                            foreach ($this->mentions as $alternativeMention) {
                                                foreach ($message->mentions as $userObj) {
                                                    if ($userObj->id == $alternativeMention->user_id) {
                                                        $mention = true;
                                                        $messageContent = str_replace("<@" . $alternativeMention->user_id . ">", "", $messageContent);
                                                        break 2;
                                                    }
                                                }
                                            }
                                        }
                                    }
                                } else {
                                    $mention = true;
                                }
                            } else {
                                $mention = false;
                            }

                            if ($mention) {
                                $limits = $this->limits->isLimited($message->guild_id, $message->channel_id, $user->id);

                                if (!empty($limits)) {
                                    foreach ($limits as $limit) {
                                        if ($limit->message !== null) {
                                            $message->reply($this->instructions->replace(array($limit->message), $object)[0]);
                                            break;
                                        }
                                    }
                                } else {
                                    $cacheKey = array(__METHOD__, $this->planID, $user->id, $messageContent);
                                    $cache = get_key_value_pair($cacheKey);

                                    if ($cache !== null) {
                                        $message->reply($cache);
                                    } else {
                                        if ($channel->require_starting_text !== null
                                            && !starts_with($messageContent, $channel->require_starting_text)
                                            || $channel->require_contained_text !== null
                                            && !str_contains($messageContent, $channel->require_contained_text)
                                            || $channel->require_ending_text !== null
                                            && !ends_with($messageContent, $channel->require_ending_text)
                                            || $channel->min_message_length !== null
                                            && strlen($messageContent) < $channel->min_message_length
                                            || $channel->max_message_length !== null
                                            && strlen($messageContent) > $channel->max_message_length) {
                                            if ($channel->failure_message !== null) {
                                                $message->reply($this->instructions->replace(array($channel->failure_message), $object)[0]);
                                            }
                                            $this->bot->processing--;
                                            return true;
                                        }
                                        if (!empty($this->keywords)) {
                                            $result = false;

                                            foreach ($this->keywords as $keyword) {
                                                if ($keyword->keyword !== null) {
                                                    if (str_contains($messageContent, $keyword->keyword)) {
                                                        $result = true;
                                                        break;
                                                    }
                                                }
                                            }

                                            if (!$result) {
                                                if ($channel->failure_message !== null) {
                                                    $message->reply($this->instructions->replace(array($channel->failure_message), $object)[0]);
                                                }
                                                $this->bot->processing--;
                                                return true;
                                            }
                                        }
                                        if ($channel->prompt_message !== null) {
                                            $promptMessage = $this->instructions->replace(array($channel->prompt_message), $object)[0];
                                        } else {
                                            $promptMessage = "...";
                                        }
                                        $message->reply($promptMessage)->done(function (Message $message)
                                        use (
                                            $object, $messageContent, $user,
                                            $threadID, $cacheKey, $logger, $channel
                                        ) {
                                            $instructions = $this->instructions->build($object);
                                            $parameters = array(
                                                "messages" => array(
                                                    array(
                                                        "role" => "system",
                                                        "content" => $instructions
                                                    ),
                                                    array(
                                                        "role" => "user",
                                                        "content" => $messageContent
                                                    )
                                                )
                                            );
                                            $reply = $this->chatAI->getResult(
                                                overflow_long(overflow_long($this->planID * 31) + (int)($user->id)),
                                                $parameters
                                            );
                                            $modelReply = $reply[2];

                                            if ($this->debug) {
                                                foreach (array($parameters, $modelReply) as $debug) {
                                                    foreach (str_split(json_encode($debug), DiscordProperties::MESSAGE_MAX_LENGTH) as $split) {
                                                        $message->reply(str_replace("\\n", DiscordProperties::NEW_LINE, $split));
                                                    }
                                                }
                                            }
                                            if ($reply[0]) {
                                                $model = $reply[1];
                                                $assistance = $this->chatAI->getText($model, $modelReply);

                                                if ($assistance !== null) {
                                                    $this->conversation->addMessage(
                                                        $message->guild_id,
                                                        $message->channel_id,
                                                        $threadID,
                                                        $user->id,
                                                        $message->id,
                                                        $messageContent,
                                                    );
                                                    $this->conversation->addReply(
                                                        $message->guild_id,
                                                        $message->channel_id,
                                                        $threadID,
                                                        $user->id,
                                                        $message->id,
                                                        $assistance,
                                                        ($modelReply->usage->prompt_tokens * $model->sent_token_cost) + ($modelReply->usage->completion_tokens * $model->received_token_cost),
                                                        $model->currency->code
                                                    );
                                                    set_key_value_pair($cacheKey, $assistance, $channel->message_retention);
                                                } else {
                                                    $logger->logError($this->planID, "Failed to get text from chat-model for plan: " . $this->planID);
                                                }
                                            } else {
                                                $assistance = null;
                                                $logger->logError($this->planID, $modelReply);
                                            }

                                            if ($assistance === null || $assistance == DiscordProperties::NO_REPLY) {
                                                if ($channel->failure_message !== null) {
                                                    $message->edit($this->instructions->replace(array($channel->failure_message), $object)[0]);
                                                } else {
                                                    $message->delete();
                                                }
                                            } else {
                                                $message->edit(MessageBuilder::new()->setContent($assistance));
                                            }
                                        });
                                    }
                                }
                            }
                            if ($channel->message_cooldown !== null) {
                                set_key_value_pair($cooldownKey, true, $channel->message_cooldown);
                            } else {
                                clear_memory(array($cooldownKey));
                            }
                        } else if ($channel->cooldown_message !== null
                            && $channel->message_cooldown !== null) {
                            $message->reply($this->instructions->replace(array($channel->cooldown_message), $object)[0]);
                        }
                    }
                } else {
                    $logger->logError($this->planID, "Failed to find chat-model for plan: " . $this->planID);
                }
                $this->bot->processing--;
                return true;
            }
        }
        $this->bot->processing--;
        return false;
    }

    public function welcome(int|string $serverID, int|string $userID): void
    {
        $this->bot->processing++;

        if (!has_memory_limit(
                array(__METHOD__, $this->planID, $serverID, $userID),
                1
            )
            && !empty($this->channels)) {
            foreach ($this->channels as $channel) {
                if ($channel->server_id == $serverID
                    && $channel->welcome_message !== null) {
                    if ($channel->whitelist === null) {
                        $channelFound = $this->discord->getChannel($channel->channel_id);

                        if ($channelFound !== null
                            && $channelFound->allowText()) {
                            $channelFound->sendMessage("<@$userID> " . $channel->welcome_message);
                        }
                    } else if (!empty($this->whitelistContents)) {
                        foreach ($this->whitelistContents as $whitelist) {
                            if ($whitelist->user_id == $userID
                                && ($whitelist->server_id === null
                                    || $whitelist->server_id === $serverID
                                    && ($whitelist->channel_id === null
                                        || $whitelist->channel_id === $channel->channel_id))) {
                                $channelFound = $this->discord->getChannel($channel->channel_id);

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
        $this->bot->processing--;
    }

}