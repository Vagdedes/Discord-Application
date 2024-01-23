<?php

use Discord\Builders\MessageBuilder;
use Discord\Parts\Channel\Message;
use Discord\Parts\Thread\Thread;

class DiscordMessageNotifications
{
    private DiscordPlan $plan;
    private array $notifications;

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
                $notification->instructions = get_sql_query(
                    BotDatabaseTable::BOT_MESSAGE_NOTIFICATION_INSTRUCTIONS,
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
                    $this->run($thread, $notification);
                    $bool = true;
                }
            }
            return $bool;
        }
        return false;
    }

    public function executeMessage(Message $message): void
    {
        if (!empty($this->notifications)) {
            foreach ($this->notifications as $notification) {
                if ($notification->is_thread === null
                    && $notification->server_id == $message->guild_id) {
                    $original = $message->channel;
                    $channel = $this->plan->utilities->getChannel($original);

                    if (($notification->category_id === null || $notification->category_id == $channel->parent_id)
                        && ($notification->channel_id === null || $notification->channel_id == $channel->id)
                        && ($notification->thread_id === null || $original instanceof Thread && $notification->thread_id == $original->id)) {
                        $this->run($message, $notification);
                    }
                }
            }
        }
    }

    private function run(Message|Thread $originalMessage, object $notification): void
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
            return;
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
                    return;
                }
            }

            if ($dealtHas && $has) {
                return;
            }
        }
        $original = $isThread ? $originalMessage : $originalMessage->channel;
        $notificationMessage = $notification->notification;
        $builder = $this->plan->listener->callNotificationMessageImplementation(
            MessageBuilder::new()->setContent($notificationMessage),
            $notification->listener_class,
            $notification->listener_method,
            $notification
        );

        $original->sendMessage($builder)->done(
            function (Message $message)
            use ($original, $notificationMessage, $notification, $isThread, $originalMessage, $date, $user) {
                $channel = $isThread ? $originalMessage->parent : $this->plan->utilities->getChannel($original);

                if ($isThread) {
                    if ($notification->lock_thread !== null) {
                        $original->locked = true;
                        $channel->threads->save($original);
                    }
                } else if ($notification->delete_message !== null) {
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
    }
}