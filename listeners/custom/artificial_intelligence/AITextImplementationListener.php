<?php

use Discord\Parts\Channel\Message;

class AITextImplementationListener // Name can be changed
{

    private const
        OWNED_PRODUCTS = "owned products / purchases";

    public static function method(object     $model,
                                  DiscordBot $bot,
                                  Message    $originalMessage,
                                  object     $channel,
                                  ?array     $localInstructions,
                                  ?array     $publicInstructions): array // Name can be changed
    {
        $account = new Account();
        $validProducts = $account->getProduct()->find(null, true, false);

        if ($validProducts->isPositiveOutcome()) {
            $validProducts = $validProducts->getObject();

            // Separator

            $account = AccountMessageCreationListener::findAccountFromSession($originalMessage);

            if ($account !== null) {
                $purchases = $account->getPurchases()->getCurrent();

                if (empty($purchases)) {
                    $bot->instructions->get($originalMessage->guild_id, $model->managerAI)->addExtra(
                        self::OWNED_PRODUCTS,
                        "You haven't made any purchases yet or they haven't been processed yet.",
                        true
                    );
                } else {
                    foreach ($purchases as $arrayKey => $value) {
                        $value = clear_object_null_keys($value);

                        foreach ($validProducts as $product) {
                            if ($value->product_id == $product->id) {
                                $value->product_name = $product->name;
                                $purchases[$arrayKey] = $value;
                                break;
                            }
                        }
                    }
                    $bot->instructions->get($originalMessage->guild_id, $model->managerAI)->addExtra(
                        self::OWNED_PRODUCTS,
                        $purchases,
                        true
                    );
                }
            } else {
                $bot->instructions->get($originalMessage->guild_id, $model->managerAI)->addExtra(
                    self::OWNED_PRODUCTS,
                    "You must log in to your Idealistic account for your potential " . self::OWNED_PRODUCTS . " to appear as information."
                    . " You current are known to have no purchases.",
                    true
                );
            }
        }
        $bot->instructions->get($originalMessage->guild_id, $model->managerAI)->addExtra(
            "time in year-month-day hours-minutes-seconds format",
            get_current_date()
        );
        return array($localInstructions, $publicInstructions);
    }
}