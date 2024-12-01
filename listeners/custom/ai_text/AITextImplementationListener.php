<?php

use Discord\Parts\Channel\Message;

class AITextImplementationListener // Name can be changed
{

    private const
        AVAILABLE_PRODUCTS = "available-products",
        OWNED_PRODUCTS = "owned-products/purchases";

    public static function method(DiscordBot $bot,
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
                    $bot->instructions->manager->addExtra(
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
                    $bot->instructions->manager->addExtra(
                        self::OWNED_PRODUCTS,
                        $purchases,
                        true
                    );
                }
            } else {
                $bot->instructions->manager->addExtra(
                    self::OWNED_PRODUCTS,
                    "You must log in to your Idealistic account for your potential " . self::OWNED_PRODUCTS . " to appear as information.",
                    true
                );
            }
        }
        return array($localInstructions, $publicInstructions);
    }
}