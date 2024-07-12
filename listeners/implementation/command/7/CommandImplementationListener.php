<?php

use Discord\Builders\MessageBuilder;
use Discord\Parts\Interactions\Interaction;

class CommandImplementationListener
{

    private static function printResult(mixed $reply): string
    {
        return ($reply->isPositiveOutcome() ? "true" : "false")
            . " | " . $reply->getMessage()
            . " | " . json_encode($reply->getObject());
    }

    public static function user_info(DiscordPlan $plan,
                                     Interaction $interaction,
                                     object      $command): void
    {
        $message = new MessageBuilder();
        $account = new Account($plan->applicationID);
        $account->getSession()->setCustomKey("discord", $interaction->data?->resolved?->users?->first()?->id);
        $object = $account->getSession()->getLastKnown();

        if ($object !== null) {
            $account = $account->getNew($object->account_id);

            if ($account->exists()) {
                $message->setContent(
                    "https://www.idealistic.ai/contents/?path=account/panel&platform=1&id="
                    . $account->getDetail("email_address")
                );
            } else {
                $message->setContent("Account not found.");
            }
        } else {
            $message->setContent("Object not found.");
        }
        $plan->utilities->acknowledgeCommandMessage(
            $interaction,
            $message,
            true
        );
    }

    public static function give_product(DiscordPlan $plan,
                                             Interaction $interaction,
                                             object      $command): void
    {
        $message = new MessageBuilder();
        $account = new Account($plan->applicationID);
        $account->getSession()->setCustomKey("discord", $interaction->data?->resolved?->users?->first()?->id);
        $object = $account->getSession()->getLastKnown();

        if ($object !== null) {
            $account = $account->getNew($object->account_id);

            if ($account->exists()) {
                $arguments = $interaction->data->options->toArray();
                $reply = $account->getPurchases()->add(
                    $arguments["product-id"]["value"]
                );
                $message->setContent(self::printResult($reply));
            } else {
                $message->setContent("Account not found.");
            }
        } else {
            $message->setContent("Object not found.");
        }
        $plan->utilities->acknowledgeCommandMessage(
            $interaction,
            $message,
            true
        );
    }

    public static function exchange_product(DiscordPlan $plan,
                                        Interaction $interaction,
                                        object      $command): void
    {
        $message = new MessageBuilder();
        $account = new Account($plan->applicationID);
        $account->getSession()->setCustomKey("discord", $interaction->data?->resolved?->users?->first()?->id);
        $object = $account->getSession()->getLastKnown();

        if ($object !== null) {
            $account = $account->getNew($object->account_id);

            if ($account->exists()) {
                $arguments = $interaction->data->options->toArray();
                $reply = $account->getPurchases()->exchange(
                    $arguments["product-id"]["value"],
                    null,
                    $arguments["exchange-product-id"]["value"]
                );
                $message->setContent(self::printResult($reply));
            } else {
                $message->setContent("Account not found.");
            }
        } else {
            $message->setContent("Object not found.");
        }
        $plan->utilities->acknowledgeCommandMessage(
            $interaction,
            $message,
            true
        );
    }

    public static function delete_account(DiscordPlan $plan,
                                          Interaction $interaction,
                                          object      $command): void
    {
        $message = new MessageBuilder();
        $account = new Account($plan->applicationID);
        $account->getSession()->setCustomKey("discord", $interaction->data?->resolved?->users?->first()?->id);
        $object = $account->getSession()->getLastKnown();

        if ($object !== null) {
            $account = $account->getNew($object->account_id);

            if ($account->exists()) {
                $arguments = $interaction->data->options->toArray();
                $reply = $account->getActions()->deleteAccount(
                    $arguments["permanent"]["value"]
                );
                $message->setContent(self::printResult($reply));
            } else {
                $message->setContent("Account not found.");
            }
        } else {
            $message->setContent("Object not found.");
        }
        $plan->utilities->acknowledgeCommandMessage(
            $interaction,
            $message,
            true
        );
    }

}