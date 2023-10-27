<?php

use Discord\Builders\Components\Option;
use Discord\Builders\Components\SelectMenu;
use Discord\Builders\MessageBuilder;
use Discord\Discord;

class AccountCreationListener // Name can be changed
{

    public static function my_account(Discord        $discord,
                                      MessageBuilder $messageBuilder): MessageBuilder // Name can be changed
    {
        $application = new Application(null);
        $products = $application->getAccount(0)->getProduct()->find(null, false);

        if ($products->isPositiveOutcome()) {
            $select = SelectMenu::new();
            $select->setMinValues(1);
            $select->setMaxValues(1);
            $select->setPlaceholder("Select a Product");

            foreach ($products->getObject() as $product) {
                if ($product->independent !== null) {
                    $option = Option::new(strip_tags($product->name), $product->id);
                    $option->setDescription(strip_tags($product->description));
                    var_dump(strlen($product->description));
                    $select->addOption($option);
                }
            }
            $messageBuilder->addComponent($select);
        }
        return $messageBuilder;
    }
}