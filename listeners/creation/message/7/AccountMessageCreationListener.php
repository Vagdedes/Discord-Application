<?php

use Discord\Builders\Components\Option;
use Discord\Builders\Components\SelectMenu;
use Discord\Builders\MessageBuilder;
use Discord\Helpers\Collection;
use Discord\Parts\Embed\Embed;
use Discord\Parts\Interactions\Interaction;

class AccountMessageCreationListener
{

    public static function my_account(DiscordPlan    $plan,
                                      MessageBuilder $messageBuilder): MessageBuilder
    {
        $application = new Application($plan->applicationID);
        $session = $application->getAccountSession();
        $account = $application->getAccount(0);
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
                    $option->setDescription(substr(DiscordSyntax::htmlToDiscord($product->description), 0, 100));
                    $select->addOption($option);
                }
            }

            // Separator

            $select->setListener(function (Interaction $interaction, Collection $options)
            use ($productObject, $plan, $select, $session) {
                if (!$plan->component->hasCooldown($select)) {
                    $product = $productObject->find($options[0]->getValue(), false);

                    if ($product->isPositiveOutcome()) {
                        $interaction->respondWithMessage(
                            self::loadProduct(
                                $interaction,
                                MessageBuilder::new(),
                                $plan,
                                $session,
                                $product->getObject()[0]
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

    private static function loadProduct(Interaction    $interaction,
                                        MessageBuilder $messageBuilder,
                                        DiscordPlan    $plan,
                                        object         $session,
                                        object         $product): MessageBuilder
    {
        $session->setCustomKey("discord", $interaction->user->id);
        $account = $session->getSession();
        $isLoggedIn = $account->isPositiveOutcome();
        $account = $account->getObject();
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

        $embed = new Embed($plan->discord);

        if ($product->color !== null) {
            $embed->setColor($product->color);
        }

        $embed->setDescription(DiscordSyntax::htmlToDiscord($product->description));

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
            $select->setPlaceholder("Select to Learn More");

            foreach ($productDivisions as $family => $divisions) {
                $divisionObject = new stdClass();
                $divisionObject->has_title = !empty($family);
                $divisionObject->title = !$divisionObject->has_title
                    ? substr(DiscordSyntax::htmlToDiscord($divisions[0]->name), 0, 100)
                    : substr(DiscordSyntax::htmlToDiscord($family), 0, 100);
                $divisionObject->contents = $divisions;

                unset($productDivisions[$family]);
                $arrayKey = string_to_integer($divisionObject->title);
                $productDivisions[$arrayKey] = $divisionObject;
                $option = Option::new($divisionObject->title, $arrayKey);
                $select->addOption($option);
            }
            $messageBuilder->addComponent($select);
            $select->setListener(function (Interaction $interaction, Collection $options)
            use ($plan, $productDivisions) {
                $reply = MessageBuilder::new();
                $embed = new Embed($plan->discord);
                $division = $productDivisions[$options[0]->getValue()];

                if ($division->has_title) {
                    $embed->setTitle($division->title);
                }

                foreach ($division->contents as $division) {
                    $embed->addFieldValues(
                        "__" . DiscordSyntax::htmlToDiscord($division->name) . "__",
                        DiscordSyntax::htmlToDiscord($division->description),
                    );
                }
                $reply->addEmbed($embed);
                $interaction->respondWithMessage($reply, true);
            }, $plan->discord);
        }
        return $messageBuilder;
    }

    public static function toggle_settings(DiscordPlan    $plan,
                                           MessageBuilder $messageBuilder): MessageBuilder
    {
        return $messageBuilder;
    }

    public static function connect_account(DiscordPlan    $plan,
                                           MessageBuilder $messageBuilder): MessageBuilder
    {
        return $messageBuilder;
    }

    // Separator
}