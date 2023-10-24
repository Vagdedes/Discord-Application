<?php

use Discord\Builders\MessageBuilder;
use Discord\Discord;
use Discord\Helpers\Collection;

class DiscordControlledMessages
{
    private DiscordPlan $plan;
    private array $messages;

    public function __construct(Discord $discord, DiscordPlan $plan)
    {
        $this->plan = $plan;
        $this->messages = get_sql_query(
            BotDatabaseTable::BOT_CONTROLLED_MESSAGES,
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

        if (!empty($this->messages)) {
            foreach ($this->messages as $arrayKey => $messageRow) {
                $channel = $discord->getChannel($messageRow->channel_id);

                if ($channel !== null) {
                    if ($messageRow->message_id === null) {
                        $channel->sendMessage($messageRow->message_content)->done(function (Message $message) use ($messageRow, $channel) {
                            $messageRow->message_id = $message->id;
                            set_sql_query(
                                BotDatabaseTable::BOT_CONTROLLED_MESSAGES,
                                array(
                                    "message_id" => $message->id
                                ),
                                array(
                                    array("id", $messageRow->id)
                                ),
                                null,
                                1
                            );
                        });
                    } else {
                        $channel->getMessageHistory([
                            'limit' => 1,
                        ])->done(function (Collection $messages) use ($discord, $messageRow, $botID) {
                            foreach ($messages as $message) {
                                if ($message->user_id == $botID
                                    && $messageRow->message_content !== $message->content) {
                                    $messageBuilder = MessageBuilder::new()->setContent($messageRow->message_content);
                                    $messageBuilder = $this->plan->component->addButtons(
                                        $discord,
                                        $messageBuilder,
                                        $messageRow->id,
                                        false
                                    );
                                    $message->edit($this->plan->component->addSelections(
                                        $discord,
                                        $messageBuilder,
                                        $messageRow->id,
                                        false
                                    ));
                                }
                            }
                        });
                    }
                } else {
                    unset($this->messages[$arrayKey]);
                }
            }
        }
    }
}