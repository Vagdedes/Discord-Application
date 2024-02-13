<?php

use Discord\Builders\MessageBuilder;
use Discord\Parts\Channel\Message;
use Discord\Parts\Thread\Thread;

class DiscordMessageNotifications
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
                    $channel = $this->plan->utilities->getChannel($original);

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
        set_sql_cache("1 second");

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

                    if ($this->plan->permissions->hasRole($user, $role->role_id)) {
                        $has = true;
                    }
                } else if ($this->plan->permissions->hasRole($user, $role->role_id)) {
                    return false;
                }
            }

            if ($dealtHas && $has) {
                return false;
            }
        }
        $original = $isThread ? $originalMessage : $originalMessage->channel;
        $object = $this->plan->instructions->getObject(
            $originalMessage->guild,
            $original,
            $user,
            $isThread ? null : $originalMessage
        );

        if (!empty($notification->localInstructions)) {
            $notificationMessage = $this->plan->aiMessages->rawTextAssistance(
                $isThread ? array($originalMessage, $user, "The user has left no information.") : $originalMessage,
                null,
                array(
                    $object,
                    $notification->localInstructions,
                    $notification->publicInstructions
                ),
                self::AI_HASH
            );

            if ($notificationMessage === null) {
                global $logger;
                $logger->logError(
                    $this->plan,
                    "Failed to get AI message for message notification with ID: " . $notification->id
                );
                $notificationMessage = $this->plan->instructions->replace(array($notification->notification), $object)[0];
            }
            $notificationMessage = MessageBuilder::new()->setContent($notificationMessage);
        } else if ($notification->message_name !== null) {
            $notificationMessage = $this->plan->persistentMessages->get($object, $notification->message_name);
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
        $lockThread = $notification->lock_thread !== null;
        $deleteMessage = $notification->delete_message !== null;

        $original->sendMessage($builder)->done(
            function (Message $message)
            use (
                $original, $notificationMessage, $notification, $isThread,
                $originalMessage, $date, $user, $lockThread, $deleteMessage
            ) {
                $channel = $isThread ? $originalMessage->parent : $this->plan->utilities->getChannel($original);

                if ($isThread) {
                    if ($lockThread) {
                        $original->locked = true;
                        $channel->threads->save($original);
                    }
                } else if ($deleteMessage) {
                    $originalMessage->delete();
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
                        "notification" => $notificationMessage,
                        "creation_date" => $date,
                        "expiration_date" => $notification->duration !== null ? get_future_date($notification->duration) : null
                    )
                )) {
                    global $logger;
                    $logger->logError(
                        $this->plan,
                        "Failed to insert channel notification with ID: " . $notification->id
                    );
                }
            }
        );
        return $lockThread || $deleteMessage;
    }
}