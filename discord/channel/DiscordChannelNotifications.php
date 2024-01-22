<?php

use Discord\Builders\MessageBuilder;
use Discord\Parts\Channel\Message;
use Discord\Parts\Thread\Thread;

class DiscordChannelNotifications
{
    private DiscordPlan $plan;
    private array $notifications;

    public function __construct(DiscordPlan $plan)
    {
        $this->plan = $plan;
        $this->notifications = get_sql_query(
            BotDatabaseTable::BOT_CHANNEL_NOTIFICATIONS,
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
            foreach ($this->notifications as $notification) {
                $notification->roles = get_sql_query(
                    BotDatabaseTable::BOT_CHANNEL_NOTIFICATION_ROLES,
                    null,
                    array(
                        array("deletion_date", null),
                        array("plan_id", $this->plan->planID),
                        null,
                        array("expiration_date", "IS", null, 0),
                        array("expiration_date", ">", get_current_date()),
                        null
                    )
                );
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
                    $thread->messages->fetch($thread->last_message_id)->done(
                        function (Message $message) use ($notification) {
                            $this->run($message, $notification);
                        }
                    );
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

    private function run(Message $message, object $notification): void
    {
        if (!empty($notification->roles)) {
            foreach ($notification->roles as $role) {
                if ($role->has_role !== $this->plan->permissions->hasRole($message->member, $role->role_id)) {
                    return;
                }
            }
        }
        $original = $message->channel;
        $notificationMessage = $notification->notification;

        $original->sendMessage(MessageBuilder::new()->setContent($notificationMessage))->done(
            function (Message $message) use ($original, $notificationMessage, $notification) {
                $channel = $this->plan->utilities->getChannel($original);

                if (!sql_insert(
                    BotDatabaseTable::BOT_CHANNEL_NOTIFICATION_TRACKING,
                    array(
                        "notification_id" => $notification->id,
                        "message_id" => $message->id,
                        "user_id" => $message->member->id,
                        "server_id" => $message->guild_id,
                        "category_id" => $channel->parent_id,
                        "channel_id" => $channel->id,
                        "thread_id" => $original instanceof Thread ? $original->id : null,
                        "notification" => $notificationMessage,
                        "creation_date" => get_current_date(),
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