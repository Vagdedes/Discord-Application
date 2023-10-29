<?php

use Discord\Builders\Components\Option;
use Discord\Builders\Components\SelectMenu;
use Discord\Builders\MessageBuilder;
use Discord\Discord;
use Discord\Helpers\Collection;
use Discord\Parts\Embed\Embed;
use Discord\Parts\Interactions\Interaction;

class AccountCreationListener
{

    public static function my_account(DiscordPlan    $plan,
                                      MessageBuilder $messageBuilder): MessageBuilder
    {
        $productObject = new Application($plan->applicationID);

        if (false) { //todo
            $isLoggedIn = true;
            $account = null;
        } else {
            $isLoggedIn = false;
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
                    $option = Option::new(substr(strip_tags($product->name), 0, 100), $product->id);
                    $option->setDescription(substr(self::htmlToDiscord($product->description), 0, 100));
                    $select->addOption($option);
                }
            }

            // Separator

            $select->setListener(function (Interaction $interaction, Collection $options)
            use ($productObject, $plan, $select, $isLoggedIn, $account) {
                if (!$plan->component->hasCooldown($select)) {
                    $product = $productObject->find($options[0]->getValue(), false);

                    if ($product->isPositiveOutcome()) {
                        $interaction->respondWithMessage(
                            self::loadProduct(
                                MessageBuilder::new(), $plan->discord,
                                $product->getObject()[0],
                                $account, $isLoggedIn
                            ),
                            true
                        );
                    }
                }
            }, $plan->discord);
            $messageBuilder->addComponent($select);
        }
        return $messageBuilder;
    }

    private static function loadProduct(MessageBuilder $messageBuilder, Discord $discord,
                                        object         $product,
                                        object         $account, bool $isLoggedIn): MessageBuilder
    {
        $productID = $product->id;
        $isFree = $product->is_free;
        $hasPurchased = $isFree
            || $isLoggedIn && $account->getPurchases()->owns($productID)->isPositiveOutcome();
        $productDivisions = $isFree
            ? array_merge($product->divisions->post_purchase, $product->divisions->pre_purchase)
            : ($hasPurchased
                ? $product->divisions->post_purchase
                : $product->divisions->pre_purchase);
        $downloadURL = $hasPurchased ? $product->download_url : null;

        // Separator

        $embed = new Embed($discord);

        if ($product->color !== null) {
            $embed->setColor($product->color);
        }
        $embed->setDescription(self::htmlToDiscord($product->description));

        if ($downloadURL) {
            $embed->setURL($downloadURL);
            $embed->setTitle("Click to download!");
        }
        if ($product->image !== null) {
            $embed->setImage($product->image);
        }
        $embed->setAuthor(
            strip_tags($product->name),
            null,
            $downloadURL
        );
        $messageBuilder->addEmbed($embed);

        // Separator

        if (!empty($productDivisions)) {
            $select = SelectMenu::new();
            $select->setMinValues(1);
            $select->setMaxValues(1);
            $select->setPlaceholder("Pick a Choice");

            foreach ($productDivisions as $family => $divisions) {
                $divisionObject = new stdClass();
                $divisionObject->has_title = !empty($family);
                $divisionObject->title = !$divisionObject->has_title
                    ? substr(self::htmlToDiscord($divisions[0]->name), 0, 100)
                    : substr(self::htmlToDiscord($family), 0, 100);
                $divisionObject->contents = $divisions;

                unset($productDivisions[$family]);
                $arrayKey = string_to_integer($divisionObject->title);
                $productDivisions[$arrayKey] = $divisionObject;
                $option = Option::new($divisionObject->title, $arrayKey);
                $select->addOption($option);
            }
            $messageBuilder->addComponent($select);
            $select->setListener(function (Interaction $interaction, Collection $options)
            use ($discord, $productDivisions) {
                $reply = MessageBuilder::new();
                $embed = new Embed($discord);
                $division = $productDivisions[$options[0]->getValue()];

                if ($division->has_title) {
                    $embed->setTitle($division->title);
                }

                foreach ($division->contents as $division) {
                    $embed->addFieldValues(
                        "__" . self::htmlToDiscord($division->name) . "__",
                        self::htmlToDiscord($division->description),
                    );
                }
                $reply->addEmbed($embed);
                $interaction->respondWithMessage($reply, true);
            }, $discord);
        }

        // Separator

        return $messageBuilder;
    }

    private static function htmlToDiscord($string): string
    {
        return strip_tags(
            str_replace("<br>", "\n",
                str_replace("<u>", "__",
                    str_replace("</u>", "__",
                        str_replace("<b>", "**",
                            str_replace("</b>", "**", $string)
                        )
                    )
                )
            )
        );
    }
}