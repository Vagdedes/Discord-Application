<?php

use Discord\Builders\MessageBuilder;
use Discord\Discord;
use Discord\Helpers\Collection;
use Discord\Parts\Channel\Message;
use Discord\Parts\Interactions\Interaction;

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
                unset($this->messages[$arrayKey]);
                $channel = $discord->getChannel($messageRow->channel_id);

                if ($channel !== null) {
                    if ($messageRow->message_id === null) {
                        $channel->sendMessage($this->build($discord, $messageRow, false))->done(
                            function (Message $message) use ($discord, $messageRow, $channel) {
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
                            }
                        );
                    } else {
                        $channel->getMessageHistory([
                            'limit' => 1,
                        ])->done(function (Collection $messages) use ($discord, $messageRow) {
                            foreach ($messages as $message) {
                                if ($message->user_id == $this->plan->botID
                                    && $message->id == $messageRow->message_id) {
                                    $message->edit($this->build($discord, $messageRow, false));
                                }
                            }
                        });
                    }
                    $this->messages[$messageRow->name] = $messageRow;
                }
            }
        }
    }

    public function sendStatic(Discord       $discord, Interaction $interaction,
                               string|object $key, bool $ephemeral): bool
    {
        $message = is_object($key) ? $key : ($this->messages[$key] ?? null);

        if ($message !== null) {
            $interaction->respondWithMessage($this->build($discord, $message), $ephemeral);
            return true;
        }
        return false;
    }

    public function sendDynamic(Discord $discord, Interaction $interaction,
                                string  $message, array $components, bool $ephemeral): bool
    {
        $messageBuilder = MessageBuilder::new()->setContent(
            empty($messageRow->message_content) ? ""
                : $messageRow->message_content
        );
        foreach ($components as $component) {
            $messageBuilder->addComponent($component);
        }
        $interaction->respondWithMessage($messageBuilder, $ephemeral);
        return true;
    }

    private function build(Discord $discord, object $messageRow, $cache = true): MessageBuilder
    {
        $messageBuilder = MessageBuilder::new()->setContent(
            empty($messageRow->message_content) ? ""
                : $messageRow->message_content
        );
        $messageBuilder = $this->plan->component->addButtons(
            $discord,
            $messageBuilder,
            $messageRow->id,
            $cache
        );
        return $this->plan->component->addSelections(
            $discord,
            $messageBuilder,
            $messageRow->id,
            $cache
        );
    }
}