<?php

use Discord\Builders\Components\ActionRow;
use Discord\Builders\Components\Button;
use Discord\Builders\Components\Option;
use Discord\Builders\Components\SelectMenu;
use Discord\Builders\MessageBuilder;
use Discord\Helpers\Collection;
use Discord\Parts\Channel\Message;
use Discord\Parts\Embed\Embed;
use Discord\Parts\Interactions\Interaction;

class AccountMessageCreationListener
{

    public static function getAttemptedAccountSession(Interaction $interaction,
                                                      DiscordPlan $plan): mixed
    {
        return get_key_value_pair(
            string_to_integer(
                $interaction->member->id
                . "attempted-account-session"
            )
        );
    }

    public static function setAttemptedAccountSession(Interaction $interaction,
                                                      DiscordPlan $plan,
                                                      object      $object): void
    {
        set_key_value_pair(
            string_to_integer(
                $interaction->member->id
                . "attempted-account-session"
            ),
            $object,
            "10 minutes"
        );
    }

    public static function clearAttemptedAccountSession(Interaction $interaction): void
    {
        clear_memory(
            array(
                string_to_integer(
                    $interaction->member->id
                    . "attempted-account-session"
                )
            ),
            false,
            1,
            function ($value) {
                return is_object($value);
            }
        );
    }

    public static function getAccountObject(Interaction|Message|null $interaction,
                                            DiscordPlan              $plan): object
    {
        $account = new Account();

        if ($interaction !== null) {
            $account->getSession()->setCustomKey("discord", $interaction->member->id);
        }
        return $account;
    }

    public static function findAccountFromSession(Interaction|Message|null $interaction,
                                                  DiscordPlan              $plan): ?object
    {
        $account = self::getAccountObject($interaction, $plan);
        $method = $account->getSession()->find();

        if ($method->isPositiveOutcome()) {
            return $method->getObject();
        } else {
            return null;
        }
    }

    public static function my_account(DiscordPlan    $plan,
                                      ?Interaction   $interaction,
                                      MessageBuilder $messageBuilder): MessageBuilder
    {
        $account = self::getAccountObject($interaction, $plan);
        $productGiveaway = $account->getProductGiveaway();
        $currentGiveawayOutcome = $productGiveaway->getCurrent(null, 1, "14 days");
        $currentGiveaway = $currentGiveawayOutcome->getObject();

        if ($currentGiveaway !== null) { // Check if current giveaway exists
            $embed = new Embed($plan->bot->discord);
            $lastGiveawayInformation = $productGiveaway->getLast();

            if ($lastGiveawayInformation->isPositiveOutcome()) { // Check if the product of the last giveaway is valid
                $lastGiveawayInformation = $lastGiveawayInformation->getObject();
                $lastGiveawayWinners = $lastGiveawayInformation[0];
                $lastGiveawayProduct = $lastGiveawayInformation[1];
                $hasWinners = !empty($lastGiveawayWinners);
                $days = max(get_date_days_difference($currentGiveaway->expiration_date), 1);
                $productToWinName = strip_tags($currentGiveaway->product->name);

                if ($hasWinners) { // Check if winners exist
                    $lastGiveawayWinners = implode(", ", $lastGiveawayWinners);
                    $description = "**" . $lastGiveawayWinners
                        . "** won the product **" . strip_tags($lastGiveawayProduct->name) . "** in the last giveaway.";
                } else {
                    $description = "";
                }
                $embed->setAuthor(
                    "GIVEAWAY | " . $productToWinName . " (" . $days . " " . ($days == 1 ? "day" : "days") . " remaining)",
                    $currentGiveaway->product->image,
                );
                $embed->setDescription($description);
                $embed->setFooter(
                    "To participate, create an account, verify your email, and finally download a product you own or will buy."
                    . " You will be included in this and all future giveaways."
                );

                // Separator

                if (false
                    && $hasWinners
                    && $currentGiveawayOutcome->isPositiveOutcome()) {
                    $announcement = MessageBuilder::new();
                    //$announcement->setContent("||@everyone||");
                    $announcementEmbed = new Embed($plan->bot->discord);
                    $announcementEmbed->setAuthor(
                        "GIVEAWAY WINNER"
                    );
                    $announcementEmbed->setTitle("Click to Participate!");
                    $announcementEmbed->setURL("");
                    $announcementEmbed->setDescription(
                        "Congratulations to **" . $lastGiveawayWinners
                        . "** for winning the product **" . strip_tags($lastGiveawayProduct->name) . "**!"
                    );
                    $announcementEmbed->setImage($lastGiveawayProduct->image);
                    $announcementEmbed->setTimestamp(time());
                    $announcement->addEmbed($announcementEmbed);
                    $channel = $plan->bot->discord->getChannel(0);

                    if ($channel !== null
                        && $channel->allowText()) {
                        $channel->sendMessage($announcement);
                    }
                }
            }
            $messageBuilder->addEmbed($embed);
        }
        return $messageBuilder;
    }

    public static function download_plugins(DiscordPlan    $plan,
                                            ?Interaction   $interaction,
                                            MessageBuilder $messageBuilder): MessageBuilder
    {
        $account = self::findAccountFromSession($interaction, $plan);

        if ($account === null) {
            $account = self::getAccountObject($interaction, $plan);
            $loggedIn = false;
        } else {
            $loggedIn = true;
        }
        $productObject = $account->getProduct();
        $products = $productObject->find(null, true, false);

        if ($products->isPositiveOutcome()) {
            $select = SelectMenu::new();
            $select->setMinValues(1);
            $select->setMaxValues(1);
            $select->setPlaceholder("Select a product to download.");

            foreach ($products->getObject() as $product) {
                if ($product->show_in_list !== null) {
                    $option = Option::new(substr(strip_tags($product->name), 0, 100), $product->id);
                    $option->setDescription(substr(DiscordSyntax::htmlToDiscord($product->description), 0, 100));
                    $select->addOption($option);
                }
            }

            $select->setListener(function (Interaction $interaction, Collection $options)
            use ($productObject, $plan, $select, $account) {
                $plan->utilities->acknowledgeMessage(
                    $interaction,
                    function () use ($plan, $interaction, $productObject, $options, $account) {
                        $product = $productObject->find($options[0]->getValue(), true, false);

                        if ($product->isPositiveOutcome()) {
                            return self::loadProduct(
                                $plan,
                                $account,
                                $product->getObject()[0]
                            );
                        } else {
                            return MessageBuilder::new()->setContent($product->getMessage());
                        }
                    },
                    true
                );
            }, $plan->bot->discord);
            $messageBuilder->addComponent($select);

            if (!$loggedIn) {
                $actionRow = ActionRow::new();
                $button = Button::new(Button::STYLE_SUCCESS)
                    ->setLabel("You Must be Logged In to Download")
                    ->setListener(function (Interaction $interaction) use ($plan) {
                        $plan->persistentMessages->send($interaction, "0-register_or_log_in", true);
                    }, $plan->bot->discord);
                $actionRow->addComponent($button);
                $messageBuilder->addComponent($actionRow);
            }
        } else {
            $messageBuilder->setContent("No products found.");
        }
        return $messageBuilder;
    }

    private static function loadProduct(DiscordPlan $plan,
                                        mixed       $account,
                                        object      $product): MessageBuilder
    {
        $messageBuilder = MessageBuilder::new();
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
        $downloadToken = $hasPurchased && $isLoggedIn ? $account->getDownloads()->findOrCreate(
            $productID,
            30,
            false,
            null,
            null
        ) : null;
        $downloadURL = $downloadToken != null && $downloadToken->isPositiveOutcome()
            ? $product->download_placeholder . "?userToken=" . $downloadToken->getObject()
            : null;
        $hasTiers = sizeof($product->tiers->paid) > 1;
        $paidTiers = $product->tiers->paid;
        $tier = array_shift($paidTiers);
        $price = $isFree
            ? null
            : ($hasTiers ? "Starting from " : "") . $tier->price . " " . $tier->currency;

        // Separator

        $embed = new Embed($plan->bot->discord);

        if ($product->color !== null) {
            $embed->setColor($product->color);
        }

        if ($downloadURL !== null) {
            $actionRow = ActionRow::new();
            $button = Button::new(Button::STYLE_LINK)
                ->setLabel(
                    "Download"
                )->setUrl(
                    $downloadURL
                );
            $actionRow->addComponent($button);
            $messageBuilder->addComponent($actionRow);

            if ($product->download_note !== null) {
                if ($price !== null) {
                    $embed->setFooter($price . " (" . DiscordSyntax::htmlToDiscord($product->download_note) . ")");
                } else {
                    $embed->setFooter(DiscordSyntax::htmlToDiscord($product->download_note));
                }
            } else {
                $embed->setFooter($price);
            }
            $canDownload = true;
        } else {
            $embed->setFooter($price);
            $canDownload = !empty($product->downloads);
        }

        $embed->setAuthor(
            strip_tags($product->name) . ($canDownload ? " (Latest Version)" : ""),
            null,
            $downloadURL
        );
        $embed->setImage($product->image);
        //$release = $product->latest_version !== null ? $product->latest_version : null;
        //$activeCustomers = $isFree ? null : ($product->registered_buyers === 0 ? null : $product->registered_buyers);
        $legalInformation = $product->legal_information !== null
            ? "[By purchasing/downloading, you acknowledge and accept this product/service's terms](" . $product->legal_information . ")"
            : null;

        $embed->addFieldValues(DiscordSyntax::htmlToDiscord($product->description), $legalInformation);
        $messageBuilder->addEmbed($embed);

        // Separator

        $productCompatibilities = $product->compatibilities;

        if (!empty($productCompatibilities)) {
            $validProducts = $account->getProduct()->find(null, true, false);
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
                    $plan->utilities->acknowledgeMessage(
                        $interaction,
                        function () use ($plan, $productDivisions, $options) {
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
                            return $reply;
                        },
                        true
                    );
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
                                function () use ($buttonObj) {
                                    return MessageBuilder::new()->setContent($buttonObj->url);
                                },
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
        return $messageBuilder;
    }

    public static function connect_account(DiscordPlan    $plan,
                                           ?Interaction   $interaction,
                                           MessageBuilder $messageBuilder,
                                           bool           $addIfEmpty = true): MessageBuilder
    {
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
            $embed = new Embed($plan->bot->discord);
            $embed->setDescription("Welcome back **" . $account->getDetail("name") . "**");

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

            // Separator

            $history = $account->getHistory()->get(
                array("action_id", "creation_date"),
                DiscordInheritedLimits::MAX_CHOICES_PER_SELECTION * min(10, DiscordInheritedLimits::MAX_FIELDS_PER_EMBED)
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
                        $plan->utilities->acknowledgeMessage(
                            $interaction,
                            function () use ($size, $plan, $interaction, $options, $history, $limit) {
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
                                    return $messageBuilder;
                                } else {
                                    return $plan->persistentMessages->get($interaction, "0-register_or_log_in");
                                }
                            },
                            true
                        );
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
        $account = self::getAccountObject($interaction, $plan);
        $accounts = $account->getRegistry()->getAccountAmount();
        $embed = new Embed($plan->bot->discord);

        if ($accounts > 0) {
            $embed->setDescription("Join **" . $accounts . "** other **" . ($accounts === 1 ? "user" : "users") . "**!");
        } else {
            $embed->setDescription("Be the first to join!");
        }
        $messageBuilder->addEmbed($embed);
        return $messageBuilder;
    }

    public static function product_advertisement(DiscordPlan    $plan,
                                                 ?Interaction   $interaction,
                                                 MessageBuilder $messageBuilder): MessageBuilder
    {
        $url = "https://discord.com/channels/289384242075533313/728076672217120808";
        $embed = new Embed($plan->bot->discord);
        $embed->setTitle("Spartan AntiCheat: Get Detection Slots!");
        $embed->setURL($url);
        $embed->setImage("https://vagdedes.com/.images/spartan/banner.png");
        $embed->setDescription("When your server/network has more Online Players than your Detection Slots,"
            . " your players will be checked in groups over time instead of all together every time the server refreshes.");
        $messageBuilder->addEmbed($embed);

        $row = ActionRow::new();
        $button = Button::new(Button::STYLE_LINK)
            ->setLabel("Start your FREE Trial today!")
            ->setURL($url);
        $row->addComponent($button);
        $messageBuilder->addComponent($row);
        return $messageBuilder;
    }

}