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

    public const
        IDEALISTIC_NAME = "www.idealistic.ai (Secure Connection)",
        IDEALISTIC_LOGO = "https://vagdedes.com/.images/idealistic/logo.png";
    private const
        VISIONARY_ID = 1195532368551878696,
        INVESTOR_ID = 1195532375677997166,
        SPONSOR_ID = 1195532379532558476,
        MOTIVATOR_ID = 1195532382363725945;

    public static function getAccountObject(?Interaction $interaction,
                                            DiscordPlan  $plan): object
    {
        $account = new Account($plan->applicationID);

        if ($interaction !== null) {
            $account->getSession()->setCustomKey("discord", $interaction->member->id);
        }
        return $account;
    }

    public static function findAccountFromSession(?Interaction $interaction,
                                                  DiscordPlan  $plan): ?object
    {
        if ($interaction === null) {
            return null;
        }
        $account = self::getAccountObject($interaction, $plan);
        $method = $account->getSession()->find();

        if ($method->isPositiveOutcome()) {
            $account = $method->getObject();

            if (!$plan->permissions->hasRole(
                $interaction->member, array(
                    self::VISIONARY_ID,
                    self::INVESTOR_ID,
                    self::SPONSOR_ID,
                    self::MOTIVATOR_ID
                )
            )) {
                $permissions = $account->getPermissions();

                if ($permissions->hasPermission("patreon.subscriber.visionary")) {
                    $plan->permissions->addDiscordRole($interaction->member, self::VISIONARY_ID);
                    $plan->permissions->removeDiscordRole($interaction->member, self::INVESTOR_ID);
                    $plan->permissions->removeDiscordRole($interaction->member, self::SPONSOR_ID);
                    $plan->permissions->removeDiscordRole($interaction->member, self::MOTIVATOR_ID);
                } else if ($permissions->hasPermission("patreon.subscriber.investor")) {
                    $plan->permissions->addDiscordRole($interaction->member, self::INVESTOR_ID);
                    $plan->permissions->removeDiscordRole($interaction->member, self::VISIONARY_ID);
                    $plan->permissions->removeDiscordRole($interaction->member, self::SPONSOR_ID);
                    $plan->permissions->removeDiscordRole($interaction->member, self::MOTIVATOR_ID);
                } else if ($permissions->hasPermission("patreon.subscriber.sponsor")) {
                    $plan->permissions->addDiscordRole($interaction->member, self::SPONSOR_ID);
                    $plan->permissions->removeDiscordRole($interaction->member, self::VISIONARY_ID);
                    $plan->permissions->removeDiscordRole($interaction->member, self::INVESTOR_ID);
                    $plan->permissions->removeDiscordRole($interaction->member, self::MOTIVATOR_ID);
                } else if ($permissions->hasPermission("patreon.subscriber.motivator")) {
                    $plan->permissions->addDiscordRole($interaction->member, self::MOTIVATOR_ID);
                    $plan->permissions->removeDiscordRole($interaction->member, self::VISIONARY_ID);
                    $plan->permissions->removeDiscordRole($interaction->member, self::INVESTOR_ID);
                    $plan->permissions->removeDiscordRole($interaction->member, self::SPONSOR_ID);
                } else {
                    $plan->permissions->removeDiscordRole($interaction->member, self::VISIONARY_ID);
                    $plan->permissions->removeDiscordRole($interaction->member, self::INVESTOR_ID);
                    $plan->permissions->removeDiscordRole($interaction->member, self::SPONSOR_ID);
                    $plan->permissions->removeDiscordRole($interaction->member, self::MOTIVATOR_ID);
                }
            }
            return $account;
        } else {
            return null;
        }
    }

    public static function my_account(DiscordPlan    $plan,
                                      ?Interaction   $interaction,
                                      MessageBuilder $messageBuilder): MessageBuilder
    {
        try {
            $account = self::findAccountFromSession($interaction, $plan);

            if ($account === null) {
                $account = self::getAccountObject($interaction, $plan);
            }
            $productObject = $account->getProduct();
            $products = $productObject->find(null, true);

            if ($products->isPositiveOutcome()) {
                $select = SelectMenu::new();
                $select->setMinValues(1);
                $select->setMaxValues(1);
                $select->setPlaceholder("Select a product to view/download.");

                foreach ($products->getObject() as $product) {
                    if ($product->independent !== null) {
                        $option = Option::new(substr(strip_tags($product->name), 0, 100), $product->id);
                        $option->setDescription(substr(DiscordSyntax::htmlToDiscord($product->description), 0, 100));
                        $select->addOption($option);
                    }
                }

                // Separator

                $select->setListener(function (Interaction $interaction, Collection $options)
                use ($productObject, $plan, $select, $account) {
                    $interaction->acknowledge()->done(function ()
                    use ($plan, $interaction, $account, $productObject, $options) {
                        $product = $productObject->find($options[0]->getValue(), true);

                        if ($product->isPositiveOutcome()) {
                            $interaction->sendFollowUpMessage(
                                self::loadProduct(
                                    $interaction,
                                    MessageBuilder::new(),
                                    $plan,
                                    $account,
                                    $product->getObject()[0]
                                ),
                                true
                            );
                        }
                    });
                }, $plan->bot->discord);
                $messageBuilder->addComponent($select);

                // Separator

                $productGiveaway = $account->getProductGiveaway();
                $currentGiveaway = $productGiveaway->getCurrent(null, 1, "14 days");

                if ($currentGiveaway->isPositiveOutcome()) { // Check if current giveaway exists
                    $embed = new Embed($plan->bot->discord);
                    $currentGiveaway = $currentGiveaway->getObject();
                    $lastGiveawayInformation = $productGiveaway->getLast();

                    if ($lastGiveawayInformation->isPositiveOutcome()) { // Check if the product of the last giveaway is valid
                        $lastGiveawayInformation = $lastGiveawayInformation->getObject();
                        $lastGiveawayWinners = $lastGiveawayInformation[0];
                        $days = max(get_date_days_difference($currentGiveaway->expiration_date), 1);
                        $productToWinName = strip_tags($currentGiveaway->product->name);
                        $nextWinnersText = $currentGiveaway->amount > 1
                            ? $currentGiveaway->amount . " winners"
                            : "the winner";

                        if (!empty($lastGiveawayWinners)) { // Check if winners exist
                            $description = "**" . implode(", ", $lastGiveawayWinners)
                                . "** recently won the product **" . strip_tags($lastGiveawayInformation[1]->name) . "**. ";
                        } else {
                            $description = "";
                        }
                        $description .= "Next giveaway will end in **" . $days . " " . ($days == 1 ? "day" : "days")
                            . "** and **$nextWinnersText** will receive **" . $productToWinName . "** for **free**.";
                        $embed->setAuthor(
                            "GIVEAWAY",
                            $currentGiveaway->product->image,
                        );
                        $embed->setTitle($productToWinName);
                        $embed->setDescription($description);
                        $embed->addFieldValues(
                            DiscordSyntax::UNDERLINE . "How to Participate" . DiscordSyntax::UNDERLINE,
                            DiscordSyntax::HEAVY_CODE_BLOCK
                            . "Create an account and verify your email. "
                            . "Then, simply download a product you own or will buy and you will be automatically included in all future giveaways."
                            . DiscordSyntax::HEAVY_CODE_BLOCK
                        );
                    }
                    $messageBuilder->addEmbed($embed);
                }
            }
        } catch (Throwable $e) {
            var_dump($e->getLine());
            var_dump($e->getMessage());
        }
        return $messageBuilder;
    }

    private static function loadProduct(Interaction    $interaction,
                                        MessageBuilder $messageBuilder,
                                        DiscordPlan    $plan,
                                        object         $account,
                                        object         $product): MessageBuilder
    {
        $isLoggedIn = $account->exists();
        $productID = $product->id;
        $isFree = $product->is_free;
        $hasPurchased = $isFree
            || $isLoggedIn && $account->getPurchases()->owns($productID)->isPositiveOutcome();
        $productDivisions = $isFree
            ? array_merge($product->divisions->post_purchase, $product->divisions->pre_purchase)
            : ($hasPurchased
                ? $product->divisions->post_purchase
                : $product->divisions->pre_purchase);
        $downloadToken = $hasPurchased && $isLoggedIn ? $account->getDownloads()->getOrCreateValidToken(
            $productID,
            1,
            true,
            false,
            DiscordProperties::SYSTEM_REFRESH_TIME,
            null
        ) : null;
        $downloadURL = $downloadToken != null && $downloadToken->isPositiveOutcome()
            ? $product->download_placeholder . "?token=" . $downloadToken->getObject()
            : null;

        // Separator

        $embed = new Embed($plan->bot->discord);

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
                        $compatibleProduct = find_object_from_key_match($validProducts, "id", $compatibility);

                        if (is_object($compatibleProduct)) {
                            $compatibleProductImage = $compatibleProduct->image;

                            if ($compatibleProductImage != null
                                && (!$isLoggedIn || !$account->getPurchases()->owns($compatibleProduct->id)->isPositiveOutcome())) {
                                $embed = new Embed($plan->bot->discord);
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
                $embed = new Embed($plan->bot->discord);
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
                    $embed = new Embed($plan->bot->discord);

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
                    $embed = new Embed($plan->bot->discord);
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
                }, $plan->bot->discord);
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
                            $plan->utilities->acknowledgeMessage(
                                $interaction,
                                MessageBuilder::new()->setContent($buttonObj->url),
                                true
                            );
                        }, $plan->bot->discord);
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
                $embed = new Embed($plan->bot->discord);
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
            $account = self::findAccountFromSession($interaction, $plan);

            if ($account !== null) {
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
                                           MessageBuilder $messageBuilder,
                                           bool           $addIfEmpty = true): MessageBuilder
    {
        if ($interaction !== null) {
            $account = self::findAccountFromSession($interaction, $plan);

            if ($account !== null) {
                foreach ($messageBuilder->getComponents() as $component) {
                    if ($component instanceof SelectMenu) {
                        $accounts = $account->getAccounts()->getAvailable(array("id", "name"));

                        foreach ($component->getOptions() as $option) {
                            $component->removeOption($option);
                        }
                        if (!empty($accounts)) {
                            $added = false;

                            foreach ($accounts as $accountObject) {
                                $description = $account->getAccounts()->getAdded(
                                    $accountObject->id,
                                    DiscordInheritedLimits::MAX_CHOICES_PER_SELECTION,
                                    true
                                );

                                if (!empty($description)) {
                                    $option = Option::new($accountObject->name, $accountObject->id);
                                    $added = true;
                                    $rows = array();

                                    foreach ($description as $row) {
                                        $rows[] = $row->credential;
                                    }
                                    $option->setDescription(substr(implode(", ", $rows), 0, 100));
                                    $component->addOption($option);
                                } else if ($addIfEmpty) {
                                    $added = true;
                                    $option = Option::new($accountObject->name, $accountObject->id);
                                    $option->setDescription("No accounts added.");
                                    $component->addOption($option);
                                }
                            }

                            if (!$added) {
                                $component->addOption(Option::new("No accounts added."));
                            }
                        } else {
                            $component->addOption(Option::new("No accounts available to add."));
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
        return self::connect_account($plan, $interaction, $messageBuilder, false);
    }

    public static function logged_in(DiscordPlan    $plan,
                                     ?Interaction   $interaction,
                                     MessageBuilder $messageBuilder): MessageBuilder
    {
        $account = self::findAccountFromSession($interaction, $plan);

        if ($account !== null) {
            global $website_domain;
            $embed = new Embed($plan->bot->discord);
            $embed->setAuthor(
                self::IDEALISTIC_NAME,
                self::IDEALISTIC_LOGO,
                $website_domain
            );
            $embed->setFooter("Support Code: " . $account->getIdentification()->get());
            $embed->setDescription("Welcome back, **" . $account->getDetail("name") . "**");

            // Separator

            $objectives = $account->getObjectives()->get();
            $size = sizeof($objectives);

            if ($size > 0) {
                $embed->addFieldValues(
                    "__**Objectives**__",
                    "You have " . $size . ($size === 1 ? " objective" : " objectives") . " to complete."
                    . " If you do not have any of the accounts, you can skip adding them without problem."
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

            // Separator

            $history = $account->getHistory()->get(
                array("action_id", "creation_date"),
                DiscordInheritedLimits::MAX_CHOICES_PER_SELECTION * DiscordInheritedLimits::MAX_FIELDS_PER_EMBED
            );

            if ($history->isPositiveOutcome()) {
                $history = $history->getObject();
                $size = sizeof($history);

                if ($size > 0) {
                    $limit = DiscordInheritedLimits::MAX_FIELDS_PER_EMBED;
                    $select = SelectMenu::new()
                        ->setMaxValues(1)
                        ->setMinValues(1)
                        ->setPlaceholder("Select a time period to view history.");

                    for ($i = 0; $i < ceil($size / $limit); $i++) {
                        $counter = $i * $limit;
                        $max = min($counter + $limit, $size);
                        $select->addOption(Option::new(
                            get_full_date($history[$counter]->creation_date)
                            . " - "
                            . get_full_date($history[$max - 1]->creation_date),
                            $i
                        ));
                    }
                    $select->setListener(function (Interaction $interaction, Collection $options)
                    use ($size, $plan, $select, $history, $limit) {
                        $account = self::findAccountFromSession($interaction, $plan);

                        if ($account !== null) {
                            $count = $options[0]->getValue();
                            $messageBuilder = MessageBuilder::new();

                            $counter = $count * $limit;
                            $max = min($counter + $limit, $size);
                            $divisor = 0;
                            $embed = new Embed($plan->bot->discord);
                            $embed->setTitle("Account History");
                            $embed->setDescription(
                                get_full_date($history[$counter]->creation_date)
                                . " - "
                                . get_full_date($history[$max - 1]->creation_date)
                            );

                            for ($x = $counter; $x < $max; $x++) {
                                $row = $history[$x];
                                $embed->addFieldValues(
                                    "__" . ($x + 1) . "__ " . str_replace("_", "-", $row->action_id),
                                    "```" . get_full_date($row->creation_date) . "```",
                                    $divisor % 3 !== 0
                                );
                                $divisor++;
                            }
                            $messageBuilder->addEmbed($embed);
                            $plan->utilities->acknowledgeMessage($interaction, $messageBuilder, true);
                        } else {
                            $messageBuilder = $plan->persistentMessages->get($interaction, "0-register_or_log_in");
                            $plan->utilities->acknowledgeMessage($interaction, $messageBuilder, true);
                        }
                    }, $plan->bot->discord);
                    $messageBuilder->addComponent($select);
                }
            }
        }
        return $messageBuilder;
    }

    public static function register_or_log_in(DiscordPlan    $plan,
                                              ?Interaction   $interaction,
                                              MessageBuilder $messageBuilder): MessageBuilder
    {
        global $website_domain;
        $account = self::getAccountObject($interaction, $plan);
        $accounts = $account->getRegistry()->getAccountAmount();
        $embed = new Embed($plan->bot->discord);
        $embed->setAuthor(
            self::IDEALISTIC_NAME,
            self::IDEALISTIC_LOGO,
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