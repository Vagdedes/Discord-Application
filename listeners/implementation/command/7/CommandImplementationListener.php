<?php

use Discord\Builders\Components\Option;
use Discord\Builders\Components\SelectMenu;
use Discord\Builders\MessageBuilder;
use Discord\Helpers\Collection;
use Discord\Parts\Interactions\Interaction;

class CommandImplementationListener
{

    private const OBJECT_NOT_FOUND = "Object not found.",
        ACCOUNT_NOT_FOUND = "Account not found.",
        LOGGED_IN = "You must be logged in.";

    private static function printResult(mixed $reply): string
    {
        return ($reply->isPositiveOutcome() ? "true" : "false")
            . " | " . $reply->getMessage()
            . " | " . json_encode($reply->getObject());
    }

    public static function account_info(DiscordPlan $plan,
                                        Interaction $interaction,
                                        object      $command): void
    {
        $message = new MessageBuilder();
        $account = new Account($plan->applicationID);
        $account->getSession()->setCustomKey("discord", $interaction->data?->resolved?->users?->first()?->id);
        $object = $account->getSession()->getLastKnown();

        if ($object !== null) {
            $account = $account->transform($object->account_id);

            if ($account->exists()) {
                $message->setContent(
                    "https://www.idealistic.ai/contents/?path=account/panel&platform=1&id="
                    . $account->getDetail("email_address")
                );
            } else {
                $message->setContent(self::ACCOUNT_NOT_FOUND);
            }
        } else {
            $message->setContent(self::OBJECT_NOT_FOUND);
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
            $account = $account->transform($object->account_id);

            if ($account->exists()) {
                $arguments = $interaction->data->options->toArray();
                $reply = $account->getPurchases()->add(
                    $arguments["product-id"]["value"]
                );
                $message->setContent(self::printResult($reply));
            } else {
                $message->setContent(self::ACCOUNT_NOT_FOUND);
            }
        } else {
            $message->setContent(self::OBJECT_NOT_FOUND);
        }
        $plan->utilities->acknowledgeCommandMessage(
            $interaction,
            $message,
            true
        );
    }

    public static function remove_product(DiscordPlan $plan,
                                          Interaction $interaction,
                                          object      $command): void
    {
        $message = new MessageBuilder();
        $account = new Account($plan->applicationID);
        $account->getSession()->setCustomKey("discord", $interaction->data?->resolved?->users?->first()?->id);
        $object = $account->getSession()->getLastKnown();

        if ($object !== null) {
            $account = $account->transform($object->account_id);

            if ($account->exists()) {
                $arguments = $interaction->data->options->toArray();
                $reply = $account->getPurchases()->remove(
                    $arguments["product-id"]["value"]
                );
                $message->setContent(self::printResult($reply));
            } else {
                $message->setContent(self::ACCOUNT_NOT_FOUND);
            }
        } else {
            $message->setContent(self::OBJECT_NOT_FOUND);
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
            $account = $account->transform($object->account_id);

            if ($account->exists()) {
                $arguments = $interaction->data->options->toArray();
                $reply = $account->getPurchases()->exchange(
                    $arguments["product-id"]["value"],
                    null,
                    $arguments["exchange-product-id"]["value"]
                );
                $message->setContent(self::printResult($reply));
            } else {
                $message->setContent(self::ACCOUNT_NOT_FOUND);
            }
        } else {
            $message->setContent(self::OBJECT_NOT_FOUND);
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
            $account = $account->transform($object->account_id);

            if ($account->exists()) {
                $arguments = $interaction->data->options->toArray();
                $reply = $account->getActions()->deleteAccount(
                    $arguments["permanent"]["value"]
                );
                $message->setContent(self::printResult($reply));
            } else {
                $message->setContent(self::ACCOUNT_NOT_FOUND);
            }
        } else {
            $message->setContent(self::OBJECT_NOT_FOUND);
        }
        $plan->utilities->acknowledgeCommandMessage(
            $interaction,
            $message,
            true
        );
    }

    public static function queue_paypal_transaction(DiscordPlan $plan,
                                                    Interaction $interaction,
                                                    object      $command): void
    {
        $arguments = $interaction->data->options->toArray();
        var_dump(strval(queue_paypal_transaction($arguments["transaction-id"]["value"])));
    }

    public static function fail_paypal_transaction(DiscordPlan $plan,
                                                   Interaction $interaction,
                                                   object      $command): void
    {
        $arguments = $interaction->data->options->toArray();
        var_dump(strval(process_failed_paypal_transaction($arguments["transaction-id"]["value"])));
    }

    public static function suspend_paypal_transactions(DiscordPlan $plan,
                                                       Interaction $interaction,
                                                       object      $command): void
    {
        $message = new MessageBuilder();
        $account = new Account($plan->applicationID);
        $account->getSession()->setCustomKey("discord", $interaction->data?->resolved?->users?->first()?->id);
        $object = $account->getSession()->getLastKnown();

        if ($object !== null) {
            $account = $account->transform($object->account_id);

            if ($account->exists()) {
                $arguments = $interaction->data->options->toArray();
                $transactions = $account->getTransactions()->getSuccessful(PaymentProcessor::PAYPAL);

                if (empty($transactions)) {
                    $message->setContent("No transactions available.");
                } else {
                    $reason = $arguments["reason"]["value"];
                    $coverFees = $arguments["cover-fees"]["value"];
                    $success = 0;
                    $failure = 0;

                    foreach ($transactions as $transaction) {
                        if (suspend_paypal_transaction(
                            $transaction->id,
                            $reason,
                            $coverFees
                        )) {
                            $success++;
                        } else {
                            $failure++;
                        }
                    }
                    $message->setContent(
                        "Success: $success | Failure: $failure | Total: " . sizeof($transactions)
                    );
                }
            } else {
                $message->setContent(self::ACCOUNT_NOT_FOUND);
            }
        } else {
            $message->setContent(self::OBJECT_NOT_FOUND);
        }
        $plan->utilities->acknowledgeCommandMessage(
            $interaction,
            $message,
            true
        );
    }

    public static function account_functionality(DiscordPlan $plan,
                                                 Interaction $interaction,
                                                 object      $command): void
    {
        $staffAccount = AccountMessageCreationListener::findAccountFromSession($interaction, $plan);
        $message = new MessageBuilder();

        if ($staffAccount === null) {
            $message->setContent(self::LOGGED_IN);
        } else {
            $account = new Account($plan->applicationID);
            $account->getSession()->setCustomKey("discord", $interaction->data?->resolved?->users?->first()?->id);
            $object = $account->getSession()->getLastKnown();

            if ($object !== null) {
                $account = $account->transform($object->account_id);

                if ($account->exists()) {
                    $functionalities = $account->getFunctionality()->getAvailable(
                        DiscordInheritedLimits::MAX_CHOICES_PER_SELECTION
                    );

                    if (empty($functionalities)) {
                        $message->setContent("No functionalities available.");
                    } else {
                        $arguments = $interaction->data->options->toArray();
                        $block = $arguments["block"]["value"];
                        $reason = $arguments["reason"]["value"];
                        $duration = $arguments["duration"]["value"];
                        $select = SelectMenu::new();
                        $select->setMinValues(1);
                        $select->setMaxValues(1);
                        $select->setPlaceholder("Select a functionality.");

                        foreach ($functionalities as $id => $functionality) {
                            $option = Option::new(substr($functionality, 0, 100), $id);
                            $select->addOption($option);
                        }

                        $select->setListener(function (Interaction $interaction, Collection $options)
                        use ($block, $reason, $duration, $plan, $account, $staffAccount) {
                            $plan->utilities->acknowledgeMessage(
                                $interaction,
                                function () use ($block, $reason, $duration, $account, $staffAccount, $options) {
                                    $functionality = $options[0]->getValue();

                                    if ($block) {
                                        $reply = $staffAccount->getFunctionality()->executeAction(
                                            $account->getDetail("id"),
                                            $functionality,
                                            $reason,
                                            !empty($duration) ? $duration : null,
                                        );
                                    } else {
                                        $reply = $staffAccount->getFunctionality()->cancelAction(
                                            $account->getDetail("id"),
                                            $functionality,
                                            $reason
                                        );
                                    }
                                    return MessageBuilder::new()->setContent(self::printResult($reply));
                                },
                                true
                            );
                        }, $plan->bot->discord, true);
                        $message->addComponent($select);
                    }
                } else {
                    $message->setContent(self::ACCOUNT_NOT_FOUND);
                }
            } else {
                $message->setContent(self::OBJECT_NOT_FOUND);
            }
            $plan->utilities->acknowledgeCommandMessage(
                $interaction,
                $message,
                true
            );
        }
    }

}