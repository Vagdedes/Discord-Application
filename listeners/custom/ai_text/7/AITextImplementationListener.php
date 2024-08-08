<?php

use Discord\Parts\Channel\Message;

class AITextImplementationListener // Name can be changed
{

    private const AVAILABLE_PRODUCTS = "available-products";

    public static function method(DiscordPlan $plan,
                                  Message     $originalMessage,
                                  object      $channel,
                                  ?array      $localInstructions,
                                  ?array      $publicInstructions): array // Name can be changed
    {
        $account = new Account();

        if (!$plan->instructions->manager->hasExtra(self::AVAILABLE_PRODUCTS)) {
            $validProducts = $account->getProduct()->find(null, true, false);

            if ($validProducts->isPositiveOutcome()) {
                $array = array();
                $validProducts = $validProducts->getObject();

                foreach ($validProducts as $arrayKey => $product) {
                    $array[$arrayKey] = $account->getProduct()->clearObjectDetails($product);
                }
                $plan->instructions->manager->addExtra(
                    self::AVAILABLE_PRODUCTS,
                    $array
                );
            }
        }

        // Separator

        $account = AccountMessageCreationListener::findAccountFromSession($originalMessage, $plan);

        if ($account !== null) {
            $plan->instructions->manager->addExtra(
                "owned-products",
                $account->getPurchases()->getCurrent(),
                true
            );
        }
        return array($localInstructions, $publicInstructions);
    }
}