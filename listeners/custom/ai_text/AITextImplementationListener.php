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

        if (!$bot->instructions->manager->hasExtra(self::AVAILABLE_PRODUCTS)) {
            $validProducts = $account->getProduct()->find(null, true, false);

            if ($validProducts->isPositiveOutcome()) {
                $array = array();
                $validProducts = $validProducts->getObject();

                foreach ($validProducts as $arrayKey => $product) {
                    $array[$arrayKey] = $account->getProduct()->clearObjectDetails($product);
                }
                $bot->instructions->manager->addExtra(
                    self::AVAILABLE_PRODUCTS,
                    $array
                );
            }
        }

        // Separator

        $account = AccountMessageCreationListener::findAccountFromSession($originalMessage);

        if ($account !== null) {
            $bot->instructions->manager->addExtra(
                self::OWNED_PRODUCTS,
                $account->getPurchases()->getCurrent(),
                true
            );
        } else {
            $bot->instructions->manager->addExtra(
                self::OWNED_PRODUCTS,
                "You must log in to your Idealistic account for your potential " . self::OWNED_PRODUCTS . " to appear as information.",
                true
            );
        }
        return array($localInstructions, $publicInstructions);
    }
}