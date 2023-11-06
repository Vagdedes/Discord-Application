<?php

use Discord\Builders\MessageBuilder;
use Discord\Parts\Channel\Message;
use \Discord\Parts\Thread\Thread;

class DiscordMessageRefresh
{
    private DiscordPlan $plan;

    public function __construct(DiscordPlan $plan)
    {
        $this->plan = $plan;
        $this->refresh();
    }

    public function refresh(): void
    {
       $query = get_sql_query(
           BotDatabaseTable::BOT_MESSAGE_REFRESH,
           null,
           array(
               array("deletion_date", null),
               null,
               array("plan_id", "IS", null, 0),
               array("plan_id", $this->plan->planID),
               null,
               null,
               array("expiration_date", "IS", null, 0),
               array("expiration_date", ">", get_current_date()),
               null
           )
       );

       if (!empty($query)) {
           foreach ($query as $row) {
               $channel = $this->plan->discord->getChannel($row->channel_id);

               if ($channel !== null
                   && $channel->guild_id == $row->server_id) {
                   if ($row->thread_id === null) {
                       $channel->sendMessage(MessageBuilder::new()->setContent($row->message_content))->done(
                           function (Message $message) use ($row) {
                               if ($row->milliseconds_retention === null) {
                                   $message->delete();
                               } else {
                                   $message->delayedDelete($row->milliseconds_retention);
                               }
                           }
                       );
                   } else {
                       foreach ($channel->threads->getIterator() as $thread) {
                           if ($thread instanceof Thread) {
                               $thread->sendMessage(MessageBuilder::new()->setContent($row->message_content))->done(
                                   function (Message $message) use ($row) {
                                       if ($row->milliseconds_retention === null) {
                                           $message->delete();
                                       } else {
                                           $message->delayedDelete($row->milliseconds_retention);
                                       }
                                   }
                               );
                           }
                       }
                   }
               }
           }
       }
    }

}