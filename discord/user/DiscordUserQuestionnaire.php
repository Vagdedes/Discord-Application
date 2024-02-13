<?php

use Discord\Builders\MessageBuilder;
use Discord\Parts\Channel\Channel;
use Discord\Parts\Channel\Message;
use Discord\Parts\Embed\Embed;
use Discord\Parts\Thread\Thread;

class DiscordUserQuestionnaire
{
    private DiscordPlan $plan;
    private array $questionnaires;
    public int $ignoreChannelDeletion, $ignoreThreadDeletion;

    private const
        FAILED_QUESTION = "Failed to find question, please contact an administrator or try again later.",
        REFRESH_TIME = "15 seconds",
        AI_HASH = 689043243;

    //todo expand feature with button & list questions

    public function __construct(DiscordPlan $plan)
    {
        global $logger;
        $this->plan = $plan;
        $this->ignoreChannelDeletion = 0;
        $this->ignoreThreadDeletion = 0;
        $this->questionnaires = array();
        $questionnaires = get_sql_query(
            BotDatabaseTable::BOT_QUESTIONNAIRES,
            null,
            array(
                array("plan_id", $plan->planID),
                array("deletion_date", null),
                null,
                array("expiration_date", "IS", null, 0),
                array("expiration_date", ">", get_current_date()),
                null
            )
        );

        if (!empty($questionnaires)) {
            foreach ($questionnaires as $questionnaire) {
                $query = get_sql_query(
                    BotDatabaseTable::BOT_QUESTIONNAIRE_QUESTIONS,
                    null,
                    array(
                        array("questionnaire_id", $questionnaire->id),
                        array("deletion_date", null),
                        null,
                        array("expiration_date", "IS", null, 0),
                        array("expiration_date", ">", get_current_date()),
                        null
                    ),
                    array(
                        "DESC",
                        "priority"
                    ),
                    DiscordInheritedLimits::MAX_FIELDS_PER_EMBED * DiscordInheritedLimits::MAX_EMBEDS_PER_MESSAGE
                );

                if (!empty($query)) {
                    $questionnaire->questions = array();

                    foreach ($query as $row) {
                        $questionnaire->questions[$row->id] = $row;
                    }
                    $questionnaire->localInstructions = get_sql_query(
                        BotDatabaseTable::BOT_QUESTIONNAIRE_INSTRUCTIONS,
                        null,
                        array(
                            array("deletion_date", null),
                            array("target_id", $questionnaire->id),
                            array("public", null),
                            null,
                            array("expiration_date", "IS", null, 0),
                            array("expiration_date", ">", get_current_date()),
                            null
                        )
                    );
                    if (!empty($questionnaire->localInstructions)) {
                        foreach ($questionnaire->localInstructions as $childKey => $instruction) {
                            $questionnaire->localInstructions[$childKey] = $instruction->instruction_id;
                        }
                    }
                    $questionnaire->publicInstructions = get_sql_query(
                        BotDatabaseTable::BOT_QUESTIONNAIRE_INSTRUCTIONS,
                        null,
                        array(
                            array("deletion_date", null),
                            array("target_id", $questionnaire->id),
                            array("public", "IS NOT", null),
                            null,
                            array("expiration_date", "IS", null, 0),
                            array("expiration_date", ">", get_current_date()),
                            null
                        )
                    );
                    if (!empty($questionnaire->publicInstructions)) {
                        foreach ($questionnaire->publicInstructions as $childKey => $instruction) {
                            $questionnaire->publicInstructions[$childKey] = $instruction->instruction_id;
                        }
                    }
                    $this->questionnaires[$questionnaire->id] = $questionnaire;
                    $this->initiate($questionnaire);
                } else {
                    $logger->logError($plan->planID, "Questionnaire without questions with ID: " . $questionnaire->id);
                }
            }
        }
    }

    private function initiate(object|string|int $query, bool $force = false): void
    {
        if (!is_object($query)) {
            if (!empty($this->questionnaires)) {
                foreach ($this->questionnaires as $questionnaire) {
                    if ($questionnaire->id == $query) {
                        $this->initiate($questionnaire);
                        break;
                    }
                }
            }
            return;
        } else {
            $this->checkExpired();

            if (!$force && $query->automatic === null
                || $query->max_open !== null
                && $this->hasMaxOpen($query->id, $query->max_open)
                && ($query->close_oldest_if_max_open === null || !$this->closeOldest($query))) {
                return;
            }
        }
        $members = null;

        foreach ($this->plan->bot->discord->guilds as $guild) {
            if ($guild->id == $query->server_id) {
                $members = $guild->members;
                break;
            }
        }

        if (empty($members)) {
            return;
        } else {
            $members = $members->toArray();
            unset($members[$this->plan->bot->botID]);
            $removalList = get_sql_query(
                BotDatabaseTable::BOT_QUESTIONNAIRE_TRACKING,
                array("user_id"),
                array(
                    array("questionnaire_id", $query->id),
                    array("creation_date", ">", get_past_date(
                        $query->cooldown_duration !== null ? $query->cooldown_duration : "1 minute"
                    ), 0),
                    array("deletion_date", null),
                    array("completion_date", null),
                    array("expired", null),
                )
            );

            if (!empty($removalList)) {
                foreach ($removalList as $removal) {
                    unset($members[$removal->user_id]);
                }
            }
        }

        if (empty($members)) {
            return;
        }
        $date = get_current_date(); // Always first

        for ($i = 0; $i < min(sizeof($members) + 1, DiscordPredictedLimits::RAPID_CHANNEL_MODIFICATIONS); $i++) {
            $key = array_rand($members);
            $member = $members[$key];
            unset($members[$key]);

            while (true) {
                $questionnaireID = random_number(19);

                if (empty(get_sql_query(
                    BotDatabaseTable::BOT_QUESTIONNAIRE_TRACKING,
                    array("questionnaire_creation_id"),
                    array(
                        array("questionnaire_creation_id", $questionnaireID)
                    ),
                    null,
                    1
                ))) {
                    $insert = array(
                        "plan_id" => $this->plan->planID,
                        "questionnaire_id" => $query->id,
                        "questionnaire_creation_id" => $questionnaireID,
                        "user_id" => $member->user->id,
                        "server_id" => $query->server_id,
                        "creation_date" => $date,
                        "expiration_date" => get_future_date($query->questionnaire_duration),
                    );

                    if ($query->create_channel_category_id !== null) {
                        $rolePermissions = get_sql_query(
                            BotDatabaseTable::BOT_QUESTIONNAIRE_ROLES,
                            array("allow", "deny", "role_id"),
                            array(
                                array("deletion_date", null),
                                array("questionnaire_id", $query->id)
                            )
                        );

                        if (!empty($rolePermissions)) {
                            foreach ($rolePermissions as $arrayKey => $role) {
                                $rolePermissions[$arrayKey] = array(
                                    "id" => $role->role_id,
                                    "type" => "role",
                                    "allow" => empty($role->allow) ? $query->allow_permission : $role->allow,
                                    "deny" => empty($role->deny) ? $query->deny_permission : $role->deny
                                );
                            }
                        }
                        $memberPermissions = array(
                            array(
                                "id" => $member->user->id,
                                "type" => "member",
                                "allow" => $query->allow_permission,
                                "deny" => $query->deny_permission
                            )
                        );
                        $this->plan->utilities->createChannel(
                            $member->guild,
                            Channel::TYPE_TEXT,
                            $query->create_channel_category_id,
                            (empty($query->create_channel_name)
                                ? $this->plan->utilities->getUsername($member->user->id)
                                : $query->create_channel_name)
                            . "-" . $questionnaireID,
                            $query->create_channel_topic,
                            $rolePermissions,
                            $memberPermissions
                        )->done(function (Channel $channel) use ($questionnaireID, $insert, $member, $query) {
                            $insert["channel_id"] = $channel->id;

                            if (sql_insert(BotDatabaseTable::BOT_QUESTIONNAIRE_TRACKING, $insert)) {
                                $question = $this->getQuestion($insert);

                                if ($question === true) {
                                    $this->closeByChannelOrThread($channel);
                                } else {
                                    $object = $this->plan->instructions->getObject(
                                        $member->guild,
                                        $channel,
                                        $member
                                    );

                                    if (is_string($question)) {
                                        $message = MessageBuilder::new()->setContent(
                                            $this->plan->instructions->replace(
                                                array($question),
                                                $object
                                            )[0]
                                        );
                                    } else {
                                        $message = $question->message_name !== null
                                            ? $this->plan->persistentMessages->get($object, $question->message_name)
                                            : MessageBuilder::new()->setContent(
                                                $this->plan->instructions->replace(
                                                    array($question->message_content),
                                                    $object
                                                )[0]
                                            );
                                    }
                                    $channel->sendMessage($message);
                                }
                            } else {
                                global $logger;
                                $logger->logError(
                                    $this->plan->planID,
                                    "(1) Failed to insert questionnaire creation with ID: " . $query->id
                                );
                            }
                        });
                    } else if ($query->create_channel_id !== null) {
                        $channel = $this->plan->bot->discord->getChannel($query->create_channel_id);

                        if ($channel !== null
                            && $channel->allowText()
                            && $channel->guild_id == $query->server_id) {
                            $question = $this->getQuestion($insert);

                            if ($question !== true) {
                                $object = $this->plan->instructions->getObject(
                                    $member->guild,
                                    $channel,
                                    $member
                                );

                                if (is_string($question)) {
                                    $message = MessageBuilder::new()->setContent(
                                        $this->plan->instructions->replace(
                                            array($question),
                                            $object
                                        )[0]
                                    );
                                } else {
                                    $message = $question->message_name !== null
                                        ? $this->plan->persistentMessages->get($object, $question->message_name)
                                        : MessageBuilder::new()->setContent(
                                            $this->plan->instructions->replace(
                                                array($question->message_content),
                                                $object
                                            )[0]
                                        );
                                }

                                $channel->startThread($message, $questionnaireID)->done(function (Thread $thread)
                                use ($insert, $member, $channel, $message, $query) {
                                    $insert["channel_id"] = $channel->id;
                                    $insert["created_thread_id"] = $thread->id;

                                    if (sql_insert(BotDatabaseTable::BOT_QUESTIONNAIRE_TRACKING, $insert)) {
                                        $channel->sendMessage($message);
                                    } else {
                                        global $logger;
                                        $logger->logError(
                                            $this->plan->planID,
                                            "(2) Failed to insert questionnaire creation with ID: " . $query->id
                                        );
                                    }
                                });
                            }
                        } else {
                            global $logger;
                            $logger->logError(
                                $this->plan->planID,
                                "Failed to find questionnaire channel with ID: " . $query->id
                            );
                        }
                    } else {
                        global $logger;
                        $logger->logError(
                            $this->plan->planID,
                            "(2) Invalid questionnaire with ID: " . $query->id
                        );
                    }
                    break;
                }
            }
        }
    }

    public function track(Message $message, object $object): bool
    {
        if (strlen($message->content) > 0) {
            $channel = $message->channel;
            set_sql_cache("1 second");
            $query = get_sql_query(
                BotDatabaseTable::BOT_QUESTIONNAIRE_TRACKING,
                array("id", "questionnaire_id", "questionnaire_creation_id", "created_thread_id",
                    "expiration_date", "deletion_date"),
                array(
                    array("server_id", $channel->guild_id),
                    array("channel_id", $channel->id),
                ),
                array(
                    "DESC",
                    "id"
                ),
                1
            );

            if (!empty($query)) {
                $query = $query[0];

                if (array_key_exists($query->questionnaire_id, $this->questionnaires)) {
                    if ($query->deletion_date !== null) {
                        if ($query->created_thread_id !== null) {
                            $this->ignoreThreadDeletion++;
                            $this->plan->utilities->deleteThread(
                                $channel,
                                $query->created_thread_id
                            );
                        } else {
                            $this->ignoreChannelDeletion++;
                            $channel->guild->channels->delete($channel);
                        }
                        $this->initiate($query->questionnaire_id);
                    } else if (get_current_date() > $query->expiration_date) {
                        if (set_sql_query(
                            BotDatabaseTable::BOT_QUESTIONNAIRE_TRACKING,
                            array(
                                "expired" => 1
                            ),
                            array(
                                array("id", $query->id)
                            ),
                            null,
                            1
                        )) {
                            if ($query->created_thread_id !== null) {
                                $this->ignoreThreadDeletion++;
                                $this->plan->utilities->deleteThread(
                                    $channel,
                                    $query->created_thread_id
                                );
                            } else {
                                $this->ignoreChannelDeletion++;
                                $channel->guild->channels->delete($channel);
                            }
                            $this->initiate($query->questionnaire_id);
                        } else {
                            global $logger;
                            $logger->logError(
                                $this->plan->planID,
                                "(1) Failed to close expired questionnaire with ID: " . $query->id
                            );
                        }
                    } else if ($message->member->id != $this->plan->bot->botID) {
                        $question = $this->getQuestion($query);

                        if ($question === true) {
                            $this->complete($message, $query, $object);
                        } else if (is_string($question)) {
                            $message->reply(MessageBuilder::new()->setContent(
                                $this->plan->instructions->replace(
                                    array($question),
                                    $object
                                )[0]
                            ));
                        } else if (set_sql_query(
                            BotDatabaseTable::BOT_QUESTIONNAIRE_ANSWERS,
                            array(
                                "answer" => $message->content,
                            ),
                            array(
                                array("deletion_date", null),
                                array("answer", null),
                                array("questionnaire_creation_id", $query->questionnaire_creation_id),
                            ),
                            null,
                            1
                        )) {
                            $question = $this->getQuestion($query);

                            if ($question === true) {
                                $this->complete($message, $query, $object);
                            } else {
                                if (is_string($question)) {
                                    $messageBuilder = MessageBuilder::new()->setContent(
                                        $this->plan->instructions->replace(array($question), $object)[0]
                                    );
                                } else {
                                    $messageBuilder = $question->message_name !== null
                                        ? $this->plan->persistentMessages->get($object, $question->message_name)
                                        : MessageBuilder::new()->setContent(
                                            $this->plan->instructions->replace(
                                                array($question->message_content),
                                                $object
                                            )[0]
                                        );
                                }
                                $message->reply($messageBuilder);
                            }
                        } else {
                            global $logger;
                            $logger->logError(
                                $this->plan->planID,
                                "Failed to insert questionnaire answer with ID: " . $query->id
                            );
                            $message->reply(MessageBuilder::new()->setContent(
                                $this->plan->instructions->replace(
                                    array($query->failure_message),
                                    $object
                                )[0]
                            ));
                        }
                        return true;
                    }
                }
            }
        }
        return false;
    }

    private function complete(Message $message, object $query, object $object): void
    {
        $answers = $this->getAnswers($query);
        $answerCount = sizeof($answers);

        if ($answerCount === 0) {
            global $logger;
            $logger->logError(
                $this->plan->planID,
                "Failed to find questionnaire answers with ID: " . $query->id
            );
            $message->reply(MessageBuilder::new()->setContent(
                $this->plan->instructions->replace(
                    array($query->failure_message),
                    $object
                )[0]
            ));
        } else {
            $questionnaire = $this->questionnaires[$query->questionnaire_id];

            if ($questionnaire->prompt_message !== null) {
                $promptMessage = $this->plan->instructions->replace(array($questionnaire->prompt_message), $object)[0];
            } else {
                $promptMessage = DiscordProperties::DEFAULT_PROMPT_MESSAGE;
            }
            $message->reply(MessageBuilder::new()->setContent(
                $promptMessage
            ))->done(function (Message $replyMessage)
            use ($message, $object, $query, $questionnaire, $answerCount, $answers) {
                $answersString = "";
                $count = 0;
                $messageBuilder = MessageBuilder::new();

                foreach (array_chunk($answers, DiscordInheritedLimits::MAX_EMBEDS_PER_MESSAGE) as $chunk) {
                    $embed = new Embed($this->plan->bot->discord);

                    foreach ($chunk as $answer) {
                        $count++;
                        $question = $questionnaire->questions[$answer->question_id]?->message_content ?? "Unknown";
                        $answersString .= "Question: " . $question . DiscordProperties::NEW_LINE;
                        $answersString .= "Answer: " . $answer->answer;

                        if ($count !== $answerCount) {
                            $answersString .= DiscordProperties::NEW_LINE . DiscordProperties::NEW_LINE;
                        }
                        $embed->setAuthor($message->author->username, $message->author->avatar);
                        $embed->addFieldValues(
                            "__" . $count . "__",
                            "Question:" . DiscordProperties::NEW_LINE . DiscordSyntax::HEAVY_CODE_BLOCK . $question . DiscordSyntax::HEAVY_CODE_BLOCK
                            . DiscordProperties::NEW_LINE
                            . "Answer:" . DiscordProperties::NEW_LINE . DiscordSyntax::HEAVY_CODE_BLOCK . $answer->answer . DiscordSyntax::HEAVY_CODE_BLOCK
                        );
                    }
                    $messageBuilder->addEmbed($embed);
                }
                if ($questionnaire->finish_message !== null) {
                    $message->author->sendMessage(MessageBuilder::new()->setContent(
                        $this->plan->instructions->replace(
                            array($questionnaire->finish_message),
                            $object
                        )[0]
                    ));

                    if ($questionnaire->outcome_channel_id !== null) {
                        $channel = $this->plan->bot->discord->getChannel($questionnaire->outcome_channel_id);

                        if ($channel !== null
                            && $channel->guild_id == $questionnaire->outcome_server_id
                            && $channel->allowText()) {
                            $channel->sendMessage($messageBuilder);
                        }
                    } else {
                        global $logger;
                        $logger->logError(
                            $this->plan->planID,
                            "Failed to find questionnaire outcome channel with ID: " . $query->id
                        );
                    }
                } else {
                    $reply = $this->plan->aiMessages->rawTextAssistance(
                        $message,
                        null,
                        array(
                            $object,
                            $questionnaire->localInstructions,
                            $questionnaire->publicInstructions
                        ),
                        self::AI_HASH
                    );

                    if ($reply !== null) {
                        $this->plan->utilities->sendMessageInPieces($message->member, $reply);
                    } else {
                        $reply = $this->plan->instructions->replace(array($questionnaire->failure_message), $object)[0];
                        $message->author->sendMessage($reply);
                    }

                    if (set_sql_query(
                        BotDatabaseTable::BOT_QUESTIONNAIRE_TRACKING,
                        array(
                            "completion_date" => get_current_date(),
                            "outcome_message" => $reply,
                        ),
                        array(
                            array("id", $query->id)
                        ),
                        null,
                        1
                    )) {
                        if ($questionnaire->outcome_channel_id !== null) {
                            $channel = $this->plan->bot->discord->getChannel($questionnaire->outcome_channel_id);

                            if ($channel !== null
                                && $channel->guild_id == $questionnaire->outcome_server_id
                                && $channel->allowText()) {
                                $channel->sendMessage($messageBuilder);
                            }
                        } else {
                            global $logger;
                            $logger->logError(
                                $this->plan->planID,
                                "Failed to find questionnaire outcome channel with ID: " . $query->id
                            );
                        }
                    } else {
                        global $logger;
                        $logger->logError(
                            $this->plan->planID,
                            "Failed to insert questionnaire completion with ID: " . $query->id
                        );
                        $message->author->sendMessage(MessageBuilder::new()->setContent(
                            $this->plan->instructions->replace(
                                array($query->failure_message),
                                $object
                            )[0]
                        ));
                    }
                }
                $this->closeByChannelOrThread($message->channel, null, null, $query);
            });
        }
    }

    // Separator

    public function closeByID(int|string $questionnaireID, int|string $userID, ?string $reason = null): ?string
    {
        set_sql_cache("1 second");
        $query = get_sql_query(
            BotDatabaseTable::BOT_QUESTIONNAIRE_TRACKING,
            array("id", "server_id", "channel_id", "created_thread_id",
                "deletion_date", "questionnaire_id", "completion_date"),
            array(
                array("questionnaire_creation_id", $questionnaireID),
            ),
            null,
            1
        );

        if (empty($query)) {
            return "Not found";
        } else {
            $query = $query[0];

            if ($query->deletion_date !== null) {
                return "Already closed";
            } else {
                try {
                    if ($query->completion_date !== null
                        || set_sql_query(
                            BotDatabaseTable::BOT_QUESTIONNAIRE_TRACKING,
                            array(
                                "deletion_date" => get_current_date(),
                                "deletion_reason" => empty($reason) ? null : $reason,
                                "deleted_by" => $userID
                            ),
                            array(
                                array("id", $query->id)
                            ),
                            null,
                            1
                        )) {
                        $channel = $this->plan->bot->discord->getChannel($query->channel_id);

                        if ($channel !== null
                            && $channel->allowText()
                            && $channel->guild_id == $query->server_id) {
                            if ($query->created_thread_id !== null) {
                                $this->ignoreThreadDeletion++;
                                $this->plan->utilities->deleteThread(
                                    $channel,
                                    $query->created_thread_id,
                                    empty($reason) ? null : $userID . ": " . $reason
                                );
                            } else {
                                $this->ignoreChannelDeletion++;
                                $channel->guild->channels->delete(
                                    $channel,
                                    empty($reason) ? null : $userID . ": " . $reason
                                );
                            }
                        }
                        $this->initiate($query->questionnaire_id);
                        return null;
                    } else {
                        return "Database query failed";
                    }
                } catch (Throwable $exception) {
                    global $logger;
                    $logger->logError($this->plan->planID, $exception->getMessage());
                    return "(Exception) " . $exception->getMessage();
                }
            }
        }
    }

    public function closeByChannelOrThread(Channel         $channel,
                                           int|string|null $userID = null,
                                           ?string         $reason = null,
                                           ?object         $query = null,
                                           bool            $delete = true): ?string
    {
        $hasQuery = $query !== null;

        if (!$hasQuery) {
            set_sql_cache("1 second");
            $query = get_sql_query(
                BotDatabaseTable::BOT_QUESTIONNAIRE_TRACKING,
                array("id", "created_thread_id", "deletion_date", "questionnaire_id", "completion_date"),
                array(
                    array("server_id", $channel->guild_id),
                    array("channel_id", $channel->id),
                ),
                null,
                1
            );
        }

        if (empty($query)) {
            return "Not found";
        } else {
            if (!$hasQuery) {
                $query = $query[0];
            }
            if ($query->deletion_date !== null) {
                return "Already closed";
            } else {
                try {
                    if ($query->completion_date !== null
                        || set_sql_query(
                            BotDatabaseTable::BOT_QUESTIONNAIRE_TRACKING,
                            array(
                                "deletion_date" => get_current_date(),
                                "deletion_reason" => empty($reason) ? null : $reason,
                                "deleted_by" => $userID
                            ),
                            array(
                                array("id", $query->id)
                            ),
                            null,
                            1
                        )) {
                        if ($delete) {
                            if ($query->created_thread_id !== null) {
                                $this->ignoreThreadDeletion++;
                                $this->plan->utilities->deleteThread(
                                    $channel,
                                    $query->created_thread_id,
                                    empty($reason) ? null : $userID . ": " . $reason
                                );
                            } else {
                                $this->ignoreChannelDeletion++;
                                $channel->guild->channels->delete(
                                    $channel,
                                    empty($reason) ? null : $userID . ": " . $reason
                                );
                            }
                        }
                        $this->initiate($query->questionnaire_id);
                        return null;
                    } else {
                        return "Database query failed";
                    }
                } catch (Throwable $exception) {
                    global $logger;
                    $logger->logError($this->plan->planID, $exception->getMessage());
                    return "(Exception) " . $exception->getMessage();
                }
            }
        }
    }

    private function closeOldest(object $questionnaire): bool
    {
        $query = get_sql_query(
            BotDatabaseTable::BOT_QUESTIONNAIRE_TRACKING,
            array("id", "server_id", "channel_id", "created_thread_id"),
            array(
                array("deletion_date", null),
                array("completion_date", null),
                array("expired", null),
                array("questionnaire_id", $questionnaire->id),
            ),
            array(
                "ASC",
                "creation_date"
            ),
            1
        );

        if (!empty($query)) {
            $query = $query[0];

            if (set_sql_query(
                BotDatabaseTable::BOT_QUESTIONNAIRE_TRACKING,
                array(
                    "deletion_date" => get_current_date(),
                ),
                array(
                    array("id", $query->id)
                ),
                null,
                1
            )) {
                $channel = $this->plan->bot->discord->getChannel($query->channel_id);

                if ($channel !== null
                    && $channel->allowText()
                    && $channel->guild_id == $query->server_id) {
                    if ($query->created_thread_id !== null) {
                        $this->ignoreThreadDeletion++;
                        $this->plan->utilities->deleteThread(
                            $channel,
                            $query->created_thread_id
                        );
                    } else {
                        $this->ignoreChannelDeletion++;
                        $channel->guild->channels->delete($channel);
                    }
                }
                return true;
            } else {
                global $logger;
                $logger->logError($this->plan->planID, "Failed to close oldest questionnaire with ID: " . $query->id);
            }
        }
        return false;
    }

    // Separator

    public function getSingle(int|string $questionnaireID, int $limitModification = 0): ?object
    {
        $cacheKey = array(__METHOD__, $this->plan->planID, $questionnaireID);
        $cache = get_key_value_pair($cacheKey);

        if ($cache !== null) {
            return $cache === false ? null : $cache;
        } else {
            $query = get_sql_query(
                BotDatabaseTable::BOT_QUESTIONNAIRE_TRACKING,
                null,
                array(
                    array("questionnaire_creation_id", $questionnaireID),
                ),
                null,
                1
            );

            if (!empty($query)) {
                $query = $query[0];
                $query->questionnaire = $this->questionnaires[$query->questionnaire_id];
                $query->answers = $this->getAnswers($query->questionnaire_id, $limitModification);

                if (!empty($query->answers)) {
                    foreach ($query->answers as $answer) {
                        $answer->question = $row->questionnaire->questions[$answer->question_id] ?? null;
                    }
                    rsort($query->answers);
                }
                set_key_value_pair($cacheKey, $query, self::REFRESH_TIME);
                return $query;
            } else {
                set_key_value_pair($cacheKey, false, self::REFRESH_TIME);
                return null;
            }
        }
    }

    public function getMultiple(int|string      $serverID, int|string $userID,
                                int|string|null $pastLookup = null, ?int $limit = null,
                                bool            $messages = true, int $limitModification = 0): array
    {
        $cacheKey = array(__METHOD__, $this->plan->planID, $userID, $pastLookup, $limit, $messages);
        $cache = get_key_value_pair($cacheKey);

        if ($cache !== null) {
            return $cache;
        } else {
            $query = get_sql_query(
                BotDatabaseTable::BOT_QUESTIONNAIRE_TRACKING,
                null,
                array(
                    array("server_id", $serverID),
                    array("user_id", $userID),
                    $pastLookup === null ? "" : array("creation_date", ">", get_past_date($pastLookup)),
                ),
                array(
                    "DESC",
                    "id"
                ),
                $limit
            );

            if (!empty($query)) {
                foreach ($query as $row) {
                    $row->questionnaire = $this->questionnaires[$row->questionnaire_id];

                    if ($messages) {
                        $row->answers = $this->getAnswers($row->questionnaire_id, $limitModification);

                        if (!empty($row->answers)) {
                            foreach ($row->answers as $answer) {
                                $answer->question = $row->questionnaire->questions[$answer->question_id] ?? null;
                            }
                            rsort($row->answers);
                        }
                    }
                }
            }
            set_key_value_pair($cacheKey, $query, self::REFRESH_TIME);
            return $query;
        }
    }

    // Separator

    public function loadSingleQuestionnaireMessage(object $questionnaire): MessageBuilder
    {
        $this->initiate($questionnaire->questionnaire_id);
        $messageBuilder = MessageBuilder::new();
        $messageBuilder->setContent("Showing questionnaire with ID **" . $questionnaire->questionnaire_creation_id . "**");

        $embed = new Embed($this->plan->bot->discord);
        $user = $this->plan->utilities->getUser($questionnaire->user_id);

        if ($user !== null) {
            $embed->setAuthor($user->id, $user->avatar);
        } else {
            $embed->setAuthor($questionnaire->user_id);
        }
        if (!empty($questionnaire->questionnaire->title)) {
            $embed->setTitle($questionnaire->questionnaire->title);
        }
        $embed->setDescription($questionnaire->completion_date !== null
            ? "Completed on " . get_full_date($questionnaire->completion_date)
            : ($questionnaire->deletion_date === null
                ? ($questionnaire->expiration_date !== null && get_current_date() > $questionnaire->expiration_date
                    ? "Expired on " . get_full_date($questionnaire->expiration_date)
                    : "Open")
                : "Closed on " . get_full_date($questionnaire->deletion_date)));
        $messageBuilder->addEmbed($embed);

        if (!empty($questionnaire->answers)) {
            $count = 0;
            $answers = $questionnaire->answers;

            foreach (array_chunk($answers, DiscordInheritedLimits::MAX_EMBEDS_PER_MESSAGE) as $chunk) {
                $embed = new Embed($this->plan->bot->discord);

                foreach ($chunk as $answer) {
                    $count++;
                    $question = $questionnaire->questions[$answer->question_id]?->message_content ?? "Unknown";
                    $embed->addFieldValues(
                        "__" . $count . "__ Question",
                        "Question:" . DiscordProperties::NEW_LINE . DiscordSyntax::HEAVY_CODE_BLOCK . $question . DiscordSyntax::HEAVY_CODE_BLOCK
                        . DiscordProperties::NEW_LINE
                        . "Answer:" . DiscordProperties::NEW_LINE . DiscordSyntax::HEAVY_CODE_BLOCK . $answer->answer . DiscordSyntax::HEAVY_CODE_BLOCK
                    );
                }
                $messageBuilder->addEmbed($embed);
            }
        }
        return $messageBuilder;
    }

    public function loadQuestionnaireMessage(int|string $userID, array $questionnaires): MessageBuilder
    {
        $this->checkExpired();
        $date = get_current_date();
        $messageBuilder = MessageBuilder::new();
        $messageBuilder->setContent("Showing last **" . sizeof($questionnaires) . " questionnaires** of user **" . $this->plan->utilities->getUsername($userID) . "**");

        foreach ($questionnaires as $questionnaire) {
            $embed = new Embed($this->plan->bot->discord);

            if (!empty($questionnaire->questionnaire->title)) {
                $embed->setTitle($questionnaire->questionnaire->title);
            }
            $embed->setDescription($questionnaire->completion_date !== null
                ? "Completed on " . get_full_date($questionnaire->completion_date)
                : ($questionnaire->deletion_date === null
                    ? ($questionnaire->expiration_date !== null && $date > $questionnaire->expiration_date
                        ? "Expired on " . get_full_date($questionnaire->expiration_date)
                        : "Open")
                    : "Closed on " . get_full_date($questionnaire->deletion_date)));
            $messageBuilder->addEmbed($embed);
        }
        return $messageBuilder;
    }

    // Separator

    private function getAnswers(object $questionnaire, int $limitModification = 0): array
    {
        return get_sql_query(
            BotDatabaseTable::BOT_QUESTIONNAIRE_ANSWERS,
            null,
            array(
                array("questionnaire_creation_id", $questionnaire->questionnaire_creation_id),
                array("deletion_date", null),
            ),
            null,
            DiscordInheritedLimits::MAX_FIELDS_PER_EMBED * (DiscordInheritedLimits::MAX_EMBEDS_PER_MESSAGE + $limitModification)
        );
    }

    private function getQuestion(array|object $questionnaireArray): object|bool|string
    {
        if (is_object($questionnaireArray)) {
            $questionnaireArray = json_decode(json_encode($questionnaireArray), true);
        }
        $questionnaire = $this->questionnaires[$questionnaireArray["questionnaire_id"]] ?? null;

        if ($questionnaire !== null) {
            $query = get_sql_query(
                BotDatabaseTable::BOT_QUESTIONNAIRE_ANSWERS,
                null,
                array(
                    array("questionnaire_creation_id", $questionnaireArray["questionnaire_creation_id"]),
                    array("deletion_date", null),
                    array("answer", null)
                ),
                null,
                1
            );

            if (empty($query)) {
                $query = get_sql_query(
                    BotDatabaseTable::BOT_QUESTIONNAIRE_ANSWERS,
                    null,
                    array(
                        array("questionnaire_creation_id", $questionnaireArray["questionnaire_creation_id"]),
                        array("deletion_date", null),
                        array("answer", "IS NOT", null)
                    )
                );

                if (empty($query)) {
                    $questions = $questionnaire->questions;
                    $question = array_shift($questions);

                    if ($question === null) {
                        return self::FAILED_QUESTION;
                    } else {
                        if (sql_insert(
                            BotDatabaseTable::BOT_QUESTIONNAIRE_ANSWERS,
                            array(
                                "questionnaire_creation_id" => $questionnaireArray["questionnaire_creation_id"],
                                "question_id" => $question->id,
                                "creation_date" => get_current_date()
                            )
                        )) {
                            return $question;
                        } else {
                            return self::FAILED_QUESTION;
                        }
                    }
                } else {
                    $questions = $questionnaire->questions;

                    foreach ($query as $row) {
                        unset($questions[$row->question_id]);
                    }
                    if (empty($questions)) {
                        return true;
                    }
                    $question = array_shift($questions);

                    if (sql_insert(
                        BotDatabaseTable::BOT_QUESTIONNAIRE_ANSWERS,
                        array(
                            "questionnaire_creation_id" => $questionnaireArray["questionnaire_creation_id"],
                            "question_id" => $question->id,
                            "creation_date" => get_current_date()
                        )
                    )) {
                        return $question;
                    } else {
                        return self::FAILED_QUESTION;
                    }
                }
            } else {
                return $questionnaire->questions[$query[0]->question_id] ?? self::FAILED_QUESTION;
            }
        } else {
            return self::FAILED_QUESTION;
        }
    }

    private function hasMaxOpen(int|string $questionnaireID, int|string $limit): bool
    {
        return sizeof(get_sql_query(
                BotDatabaseTable::BOT_QUESTIONNAIRE_TRACKING,
                array("id"),
                array(
                    array("questionnaire_id", $questionnaireID),
                    array("deletion_date", null),
                    array("completion_date", null),
                    array("expired", null),
                    null,
                    array("expiration_date", "IS", null, 0),
                    array("expiration_date", ">", get_current_date()),
                    null
                ),
                array(
                    "DESC",
                    "id"
                ),
                $limit
            )) == $limit;
    }

    private function checkExpired(): void
    {
        $query = get_sql_query(
            BotDatabaseTable::BOT_QUESTIONNAIRE_TRACKING,
            null,
            array(
                array("plan_id", $this->plan->planID),
                array("deletion_date", null),
                array("completion_date", null),
                array("expired", null),
                array("expiration_date", "<", get_current_date())
            ),
            array(
                "DESC",
                "id"
            ),
            DiscordPredictedLimits::RAPID_CHANNEL_MODIFICATIONS // Limit so we don't ping Discord too much
        );

        if (!empty($query)) {
            foreach ($query as $row) {
                if (set_sql_query(
                    BotDatabaseTable::BOT_QUESTIONNAIRE_TRACKING,
                    array(
                        "expired" => 1
                    ),
                    array(
                        array("id", $row->id)
                    ),
                    null,
                    1
                )) {
                    $channel = $this->plan->bot->discord->getChannel($row->channel_id);

                    if ($channel !== null
                        && $channel->allowText()
                        && $channel->guild_id == $row->server_id) {
                        if ($row->created_thread_id !== null) {
                            $this->ignoreThreadDeletion++;
                            $this->plan->utilities->deleteThread(
                                $channel,
                                $row->created_thread_id
                            );
                        } else {
                            $this->ignoreChannelDeletion++;
                            $channel->guild->channels->delete($channel);
                        }
                    }
                } else {
                    global $logger;
                    $logger->logError(
                        $this->plan->planID,
                        "(2) Failed to close expired questionnaire with ID: " . $row->id
                    );
                }
            }
        }
    }

}