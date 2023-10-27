<?php

use Discord\Builders\Components\Option;
use Discord\Builders\Components\SelectMenu;
use Discord\Builders\MessageBuilder;
use Discord\Helpers\Collection;
use Discord\Parts\Embed\Embed;
use Discord\Parts\Interactions\Interaction;

class AccountCreationListener
{

    public static function my_account(DiscordPlan    $plan,
                                      MessageBuilder $messageBuilder): MessageBuilder
    {
        $productObject = new Application(null);

        if (false) { //todo
            $loggedIn = true;
            $account = null;
        } else {
            $loggedIn = false;
            $account = $productObject->getAccount(0);
        }
        $productObject = $account->getProduct();
        $products = $productObject->find(null, false);

        if ($products->isPositiveOutcome()) {
            $select = SelectMenu::new();
            $select->setMinValues(1);
            $select->setMaxValues(1);
            $select->setPlaceholder("Select a Product");

            foreach ($products->getObject() as $product) {
                if ($product->independent !== null) {
                    $option = Option::new(strip_tags($product->name), $product->id);
                    $option->setDescription(strip_tags(substr($product->description, 0, 100)));
                    $select->addOption($option);
                }
            }
            $select->setListener(function (Interaction $interaction, Collection $options)
            use ($productObject, $plan, $select) {
                if (!$plan->component->hasCooldown($select)) {
                    $product = $productObject->find($options[0]->getValue(), false);

                    if ($product->isPositiveOutcome()) {
                        $product = $product->getObject()[0];
                        $reply = MessageBuilder::new();
                        $content = "";
                        $reply->setContent($content);
                        $embed = new Embed($plan->discord);
                        $embed->setDescription(strip_tags($product->description));
                        var_dump($product->download_url);
                        $embed->setAuthor($product->name, $product->image, $product->download_url);
                        $reply->addEmbed($embed);
                        $interaction->respondWithMessage(
                            $reply,
                            true
                        );
                    }
                }
            }, $plan->discord);
            $messageBuilder->addComponent($select);
        }
        return $messageBuilder;
    }
}