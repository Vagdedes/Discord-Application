<?php

use Discord\Builders\MessageBuilder;
use Discord\Helpers\Collection;
use Discord\Parts\Channel\Message;
use Discord\Parts\Thread\Thread;

class DiscordNotificationMessages
{
    private DiscordPlan $plan;
    private array $notifications;

    private const AI_HASH = 634512434;

    public function __construct(DiscordPlan $plan)
    {
        $this->plan = $plan;
        $this->notifications = get_sql_query(
            BotDatabaseTable::BOT_MESSAGE_NOTIFICATIONS,
            null,
            array(
                array("deletion_date", null),
                array("plan_id", $this->plan->planID),
                null,
                array("expiration_date", "IS", null, 0),
                array("expiration_date", ">", get_current_date()),
                null
            ),
            array(
                "DESC",
                "priority"
            )
        );

        if (!empty($this->notifications)) {
            foreach ($this->notifications as $arrayKey => $notification) {
                $notification->roles = get_sql_query(
                    BotDatabaseTable::BOT_MESSAGE_NOTIFICATION_ROLES,
                    null,
                    array(
                        array("deletion_date", null),
                        array("notification_id", $notification->id),
                        null,
                        array("expiration_date", "IS", null, 0),
                        array("expiration_date", ">", get_current_date()),
                        null
                    )
                );
                $notification->localInstructions = get_sql_query(
                    BotDatabaseTable::BOT_MESSAGE_NOTIFICATION_INSTRUCTIONS,
                    null,
                    array(
                        array("deletion_date", null),
                        array("notification_id", $notification->id),
                        array("public", null),
                        null,
                        array("expiration_date", "IS", null, 0),
                        array("expiration_date", ">", get_current_date()),
                        null
                    )
                );
                if (!empty($notification->localInstructions)) {
                    foreach ($notification->localInstructions as $childKey => $instruction) {
                        $notification->localInstructions[$childKey] = $instruction->instruction_id;
                    }
                }
                $notification->publicInstructions = get_sql_query(
                    BotDatabaseTable::BOT_MESSAGE_NOTIFICATION_INSTRUCTIONS,
                    null,
                    array(
                        array("deletion_date", null),
                        array("notification_id", $notification->id),
                        array("public", "IS NOT", null),
                        null,
                        array("expiration_date", "IS", null, 0),
                        array("expiration_date", ">", get_current_date()),
                        null
                    )
                );
                if (!empty($notification->publicInstructions)) {
                    foreach ($notification->publicInstructions as $childKey => $instruction) {
                        $notification->publicInstructions[$childKey] = $instruction->instruction_id;
                    }
                }
                $this->notifications[$arrayKey] = $notification;
            }
        }
    }

    public function executeThread(Thread $thread): bool
    {
        if (!empty($this->notifications)) {
            $bool = false;

            foreach ($this->notifications as $notification) {
                if ($notification->is_thread !== null
                    && $notification->server_id == $thread->guild_id
                    && ($notification->category_id === null || $notification->category_id == $thread->parent->parent_id)
                    && ($notification->channel_id === null || $notification->channel_id == $thread->parent_id)) {
                    $bool |= $this->run($thread, $notification);
                }
            }
            return $bool;
        }
        return false;
    }

    public function executeMessage(Message $message): bool
    {
        if (!empty($this->notifications)) {
            $bool = false;

            foreach ($this->notifications as $notification) {
                if ($notification->is_thread === null
                    && $notification->server_id == $message->guild_id) {
                    $original = $message->channel;
                    $channel = $this->plan->utilities->getChannelOrThread($original);

                    if (($notification->category_id === null || $notification->category_id == $channel->parent_id)
                        && ($notification->channel_id === null || $notification->channel_id == $channel->id)
                        && ($notification->thread_id === null || $original instanceof Thread && $notification->thread_id == $original->id)) {
                        $bool |= $this->run($message, $notification);
                    }
                }
            }
            return $bool;
        }
        return false;
    }

    private function run(Message|Thread $originalMessage, object $notification): bool
    {
        $date = get_current_date();
        $isThread = $originalMessage instanceof Thread;
        $user = $isThread ? $originalMessage->owner_member : $originalMessage->member;

        if (!empty(get_sql_query(
            BotDatabaseTable::BOT_MESSAGE_NOTIFICATION_TRACKING,
            array("notification_id"),
            array(
                array("user_id", $user->id),
                array("notification_id", $notification->id),
                array("deletion_date", null),
                array("expiration_date", "IS NOT", null),
                array("expiration_date", ">", $date),
            ),
            null,
            1
        ))) {
            return false;
        }

        if (!empty($notification->roles)) {
            $dealtHas = false;
            $has = false;

            foreach ($notification->roles as $role) {
                if ($role->has_role !== null) {
                    $dealtHas = true;

                    if ($this->plan->bot->permissions->hasRole($user, $role->role_id)) {
                        $has = true;
                    }
                } else if ($this->plan->bot->permissions->hasRole($user, $role->role_id)) {
                    return false;
                }
            }

            if ($dealtHas && $has) {
                return false;
            }
        }
        $lockThread = $notification->lock_thread !== null;
        $deleteMessage = $notification->delete_message !== null;
        $original = $isThread ? $originalMessage : $originalMessage->channel;
        $callable = function (Message $originalMessage, ?Thread $thread = null)
        use ($notification, $isThread, $original, $user, $date, $lockThread, $deleteMessage) {
            $object = $this->plan->instructions->getObject(
                $originalMessage->guild,
                $original,
                $user,
                $originalMessage
            );
            if ($notification->ai_model_id !== null) {
                $notificationMessage = $this->plan->aiMessages->rawTextAssistance(
                    $notification->ai_model_id,
                    $originalMessage,
                    null,
                    array(
                        $object,
                        empty($notification->localInstructions) ? null : $notification->localInstructions,
                        empty($notification->publicInstructions) ? null : $notification->publicInstructions,
                        null
                    ),
                    self::AI_HASH
                );

                if ($notificationMessage === null) {
                    global $logger;
                    $logger->logError(
                        "Failed to get AI message for message notification with ID: " . $notification->id
                    );
                    $notificationMessage = $this->plan->instructions->replace(array($notification->notification), $object)[0];
                }
                $notificationMessage = $this->plan->persistentMessages->get($object, $notification->message_name)
                    ->setContent($notificationMessage);
            } else if ($notification->message_name !== null) {
                $notificationMessage = $this->plan->persistentMessages->get($object, $notification->message_name);

                if ($notification->notification !== null) {
                    $notificationMessage->setContent(
                        $this->plan->instructions->replace(array($notification->notification), $object)[0]
                    );
                }
            } else {
                $notificationMessage = MessageBuilder::new()->setContent(
                    $this->plan->instructions->replace(array($notification->notification), $object)[0]
                );
            }
            $builder = $this->plan->listener->callNotificationMessageImplementation(
                $notificationMessage,
                $notification->listener_class,
                $notification->listener_method,
                $notification
            );

            $original->sendMessage($builder)->done(
                function (Message $message)
                use (
                    $original, $thread, $notification, $isThread,
                    $originalMessage, $date, $user, $lockThread, $deleteMessage
                ) {
                    $channel = $isThread ? $thread->parent : $this->plan->utilities->getChannelOrThread($original);

                    if ($isThread) {
                        if ($lockThread) {
                            $original->locked = true;
                            $channel->threads->save($original);
                        }
                    }
                    if ($deleteMessage) {
                        $originalMessage->delete();
                    }
                    if ($notification->feedback !== null) {
                        $this->plan->component->addReactions($message, DiscordAIMessages::REACTION_COMPONENT_NAME);
                    }
                    if (!sql_insert(
                        BotDatabaseTable::BOT_MESSAGE_NOTIFICATION_TRACKING,
                        array(
                            "notification_id" => $notification->id,
                            "message_id" => $message->id,
                            "user_id" => $user->id,
                            "server_id" => $originalMessage->guild_id,
                            "category_id" => $channel->parent_id,
                            "channel_id" => $channel->id,
                            "thread_id" => $isThread || $original instanceof Thread ? $original->id : null,
                            "notification" => $message->content,
                            "creation_date" => $date,
                            "expiration_date" => $notification->duration !== null ? get_future_date($notification->duration) : null
                        )
                    )) {
                        global $logger;
                        $logger->logError(
                            "Failed to insert channel notification with ID: " . $notification->id
                        );
                    }
                }
            );
        };

        if ($isThread) {
            $originalMessage->getMessageHistory(['limit' => 1])->done(function (Collection $messages) use ($callable, $originalMessage) {
                foreach ($messages as $message) {
                    $callable($message, $originalMessage);
                }
            });
            return true;
        } else {
            $callable($originalMessage);
            return $lockThread || $deleteMessage;
        }
    }
}