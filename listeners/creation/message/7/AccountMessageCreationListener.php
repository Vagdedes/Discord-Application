<?php

use Discord\Builders\Components\ActionRow;
use Discord\Builders\Components\Button;
use Discord\Builders\Components\Option;
use Discord\Builders\Components\SelectMenu;
use Discord\Builders\MessageBuilder;
use Discord\Helpers\Collection;
use Discord\Parts\Embed\Embed;
use Discord\Parts\Interactions\Interaction;

class AccountMessageCreationListener
{

    public static function my_account(DiscordPlan    $plan,
                                      ?Interaction   $interaction,
                                      MessageBuilder $messageBuilder): MessageBuilder
    {
        $account = new Account($plan->applicationID);
        $session = $account->getSession();
        $productObject = $account->getProduct();
        $products = $productObject->find(null, true);

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
                    $interaction->acknowledge()->done(function ()
                    use ($plan, $interaction, $session, $productObject, $options) {
                        $product = $productObject->find($options[0]->getValue(), true);

                        if ($product->isPositiveOutcome()) {
                            $interaction->sendFollowUpMessage(
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
                    });
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
        $downloadToken = $account->getDownloads()->getOrCreateValidToken($productID, 1, true);
        $downloadURL = $hasPurchased && $downloadToken->isPositiveOutcome()
            ? $product->download_placeholder . "?token=" . $downloadToken->getObject()
            : null;

        // Separator

        $embed = new Embed($plan->discord);

        if ($product->color !== null) {
            $embed->setColor($product->color);
        }

        $embed->setDescription(DiscordSyntax::htmlToDiscord($product->description));

        if ($downloadURL) {
            $embed->setURL($downloadURL);
            $embed->setTitle("Click to Download"
                . ($product->download_note !== null
                    ? " (" . DiscordSyntax::htmlToDiscord($product->download_note) . ")"
                    : ""));
        }
        if ($product->image !== null) {
            $embed->setImage($product->image);
        }
        $embed->setAuthor(
            strip_tags($product->name),
            null,
            $downloadURL
        );
        $release = $product->latest_version !== null ? $product->latest_version : null;
        $hasTiers = sizeof($product->tiers->paid) > 1;
        $tier = array_shift($product->tiers->paid);
        $price = $isFree ? null : ($hasTiers ? "Starting from " : "") . $tier->price . " " . $tier->currency;
        $activeCustomers = $isFree ? null : ($product->registered_buyers === 0 ? null : $product->registered_buyers);
        $legalInformation = $product->legal_information !== null
            ? "[By purchasing/downloading, you acknowledge and accept this product/service's __legal information__](" . $product->legal_information . ")"
            : null;

        foreach (array(
                     "On Development For" => get_date_days_difference($product->creation_date) . " Days",
                     "Last Version Release" => $release,
                     "Price" => $price,
                     "Customers" => $activeCustomers,
                     "Legal Information" => $legalInformation
                 ) as $arrayKey => $arrayValue) {
            if ($arrayValue !== null) {
                $embed->addFieldValues($arrayKey, $arrayValue);
            }
        }
        $messageBuilder->addEmbed($embed);

        // Separator

        $offer = $product->show_offer;

        if ($offer === null) {
            $productCompatibilities = $product->compatibilities;

            if (!empty($productCompatibilities)) {
                $validProducts = $account->getProduct()->find();
                $validProducts = $validProducts->getObject();

                if (sizeof($validProducts) > 1) { // One because we already are quering one
                    foreach ($productCompatibilities as $compatibility) {
                        //$product->compatibility_description
                        $compatibleProduct = find_object_from_key_match($validProducts, "id", $compatibility);

                        if (is_object($compatibleProduct)) {
                            $compatibleProductImage = $compatibleProduct->image;

                            if ($compatibleProductImage != null) {
                                $embed = new Embed($plan->discord);
                                $embed->setTitle(strip_tags($compatibleProduct->name));

                                if ($compatibleProduct->color !== null) {
                                    $embed->setColor($compatibleProduct->color);
                                }
                                $embed->setDescription(DiscordSyntax::htmlToDiscord($compatibleProduct->description));
                                $embed->setAuthor(
                                    $product->compatibility_description,
                                    $compatibleProduct->image,
                                );
                                $messageBuilder->addEmbed($embed);
                            }
                        }
                    }
                }
            }
        } else {
            $offer = $account->getProductOffer()->find($offer == -1 ? null : $offer);

            if ($offer->isPositiveOutcome()) {
                $offer = $offer->getObject();
                $embed = new Embed($plan->discord);
                $embed->setAuthor(
                    strip_tags($offer->name),
                    $offer->image
                );
                if ($offer->description !== null) {
                    $embed->setTitle(DiscordSyntax::htmlToDiscord($offer->description));
                }
                $contents = "";

                foreach ($offer->divisions as $divisions) {
                    foreach ($divisions as $division) {
                        $contents .= $division->description;
                    }
                }
                $embed->setDescription(DiscordSyntax::htmlToDiscord($contents));
                $messageBuilder->addEmbed($embed);
            }
        }

        // Separator

        if (!empty($productDivisions)) {
            if (sizeof($productDivisions) === 1) {
                foreach ($productDivisions as $family => $divisions) {
                    $embed = new Embed($plan->discord);

                    if ($family !== null) {
                        $embed->setTitle($family);
                    }
                    foreach ($divisions as $division) {
                        $embed->addFieldValues(
                            "__" . DiscordSyntax::htmlToDiscord($division->name) . "__",
                            DiscordSyntax::htmlToDiscord($division->description),
                        );
                    }
                    $messageBuilder->addEmbed($embed);
                }
            } else {
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
                    $plan->utilities->acknowledgeMessage($interaction, $reply, true);
                }, $plan->discord);
            }
        }

        // Separator

        $productButtons = $hasPurchased ? $product->buttons->post_purchase : $product->buttons->pre_purchase;

        if (!empty($productButtons)) {
            $actionRow = ActionRow::new();

            foreach ($productButtons as $buttonObj) {
                if ($isLoggedIn
                    || $buttonObj->requires_account === null) {
                    switch ($buttonObj->color) {
                        case "red":
                            $button = Button::new(Button::STYLE_DANGER)->setLabel(
                                $buttonObj->name
                            );
                            break;
                        case "green":
                            $button = Button::new(Button::STYLE_SUCCESS)
                                ->setLabel(
                                    $buttonObj->name
                                );
                            break;
                        case "blue":
                            $button = Button::new(Button::STYLE_PRIMARY)
                                ->setLabel(
                                    $buttonObj->name
                                );
                            break;
                        case "gray":
                            $button = Button::new(Button::STYLE_SECONDARY)
                                ->setLabel(
                                    $buttonObj->name
                                );
                            break;
                        default:
                            $button = null;
                            break;
                    }

                    if ($button !== null) {
                        $button->setListener(function (Interaction $interaction)
                        use ($plan, $actionRow, $buttonObj) {
                            if (!$plan->component->hasCooldown($actionRow)) {
                                $plan->utilities->acknowledgeMessage(
                                    $interaction,
                                    MessageBuilder::new()->setContent($buttonObj->url),
                                    true
                                );
                            }
                        }, $plan->discord);
                        $actionRow->addComponent($button);
                    }
                }
            }
            $messageBuilder->addComponent($actionRow);
        }

        // Separator

        $productCards = $hasPurchased ? $product->cards->post_purchase : $product->cards->pre_purchase;

        if (!empty($productCards)) {
            foreach ($productCards as $card) {
                $embed = new Embed($plan->discord);
                $embed->setAuthor(
                    strip_tags($card->name),
                    $card->image,
                    $card->url
                );
                $messageBuilder->addEmbed($embed);
            }
        }
        return $messageBuilder;
    }

    public static function toggle_settings(DiscordPlan    $plan,
                                           ?Interaction   $interaction,
                                           MessageBuilder $messageBuilder): MessageBuilder
    {
        if ($interaction !== null) {
            $account = AccountMessageImplementationListener::getAccountSession($plan, $interaction->user->id);
            $account = $account->getSession();

            if ($account->isPositiveOutcome()) {
                $account = $account->getObject();

                foreach ($messageBuilder->getComponents() as $component) {
                    if ($component instanceof SelectMenu) {
                        foreach ($component->getOptions() as $option) {
                            if ($option instanceof Option) {
                                $option->setDescription(
                                    $account->getSettings()->isEnabled($option->getValue())
                                        ? "Enabled"
                                        : "Disabled"
                                );
                            }
                        }
                        break;
                    }
                }
            } else {
                $messageBuilder = $plan->persistentMessages->get($interaction, "0-register_or_log_in");
            }
        }
        return $messageBuilder;
    }

    public static function connect_account(DiscordPlan    $plan,
                                           ?Interaction   $interaction,
                                           MessageBuilder $messageBuilder): MessageBuilder
    {
        if ($interaction !== null) {
            $account = AccountMessageImplementationListener::getAccountSession($plan, $interaction->user->id);
            $account = $account->getSession();

            if ($account->isPositiveOutcome()) {
                $account = $account->getObject();

                foreach ($messageBuilder->getComponents() as $component) {
                    if ($component instanceof SelectMenu) {
                        $accounts = $account->getAccounts()->getAvailable(array("id", "name"));

                        foreach ($component->getOptions() as $option) {
                            $component->removeOption($option);
                        }
                        if (!empty($accounts)) {
                            foreach ($accounts as $accountObject) {
                                $option = Option::new($accountObject->name, $accountObject->id);
                                $description = $account->getAccounts()->getAdded($accountObject->id, 5);

                                if (!empty($description)) {
                                    $rows = array();

                                    foreach ($description as $row) {
                                        $rows[] = $row->credential;
                                    }
                                    $description = substr(implode(", ", $rows), 0, 100);
                                } else {
                                    $description = "No accounts added.";
                                }
                                $option->setDescription($description);
                                $component->addOption($option);
                            }
                        } else {
                            $component->addOption(Option::new("No accounts available."));
                        }
                        break;
                    }
                }
            } else {
                $messageBuilder = $plan->persistentMessages->get($interaction, "0-register_or_log_in");
            }
        }
        return $messageBuilder;
    }

    public static function disconnect_account(DiscordPlan    $plan,
                                              ?Interaction   $interaction,
                                              MessageBuilder $messageBuilder): MessageBuilder
    {
        return self::connect_account($plan, $interaction, $messageBuilder);
    }

    public static function logged_in(DiscordPlan    $plan,
                                     ?Interaction   $interaction,
                                     MessageBuilder $messageBuilder): MessageBuilder
    {
        $account = AccountMessageImplementationListener::getAccountSession($plan, $interaction->user->id);
        $account = $account->getSession();

        if ($account->isPositiveOutcome()) {
            global $website_domain;
            $account = $account->getObject();
            $embed = new Embed($plan->discord);
            $embed->setAuthor(
                AccountMessageImplementationListener::IDEALISTIC_NAME,
                AccountMessageImplementationListener::IDEALISTIC_LOGO,
                $website_domain
            );
            $embed->setDescription("Welcome back, **" . $account->getDetail("name") . "**");

            // Separator

            $objectives = $account->getObjectives()->get();
            $size = sizeof($objectives);

            if ($size > 0) {
                $embed->addFieldValues(
                    "__**Objectives**__",
                    "You have " . $size . ($size === 1 ? " objective" : " objectives") . " to complete."
                );
                foreach ($objectives as $count => $objective) {
                    $hasURL = !$objective->optional_url && $objective->url !== null;
                    $embed->addFieldValues(
                        "__" . ($count + 1) . "__ " . $objective->title,
                        ($hasURL ? "[" : "")
                        . $objective->description
                        . ($hasURL ? "](" . $objective->url . ")" : "")
                    );
                }
            }
            $messageBuilder->addEmbed($embed);
        }
        return $messageBuilder;
    }

    public static function register_or_log_in(DiscordPlan    $plan,
                                              ?Interaction   $interaction,
                                              MessageBuilder $messageBuilder): MessageBuilder
    {
        global $website_domain;
        $account = new Account($plan->applicationID);
        $accounts = $account->getRegistry()->getAccountAmount();
        $embed = new Embed($plan->discord);
        $embed->setAuthor(
            AccountMessageImplementationListener::IDEALISTIC_NAME,
            AccountMessageImplementationListener::IDEALISTIC_LOGO,
            $website_domain
        );

        if ($accounts > 0) {
            $embed->setDescription("Join **" . $accounts . "** other **" . ($accounts === 1 ? "user" : "users") . "**!");
        } else {
            $embed->setDescription("Be the first to join!");
        }
        $messageBuilder->addEmbed($embed);
        return $messageBuilder;
    }
}