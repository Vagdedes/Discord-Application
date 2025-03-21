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

    public static function getAttemptedAccountSession(Interaction $interaction): mixed
    {
        return get_key_value_pair(
            string_to_integer(
                $interaction->member->id
                . "attempted-account-session"
            )
        );
    }

    public static function setAttemptedAccountSession(Interaction $interaction,
                                                      object      $object): void
    {
        set_key_value_pair(
            string_to_integer(
                $interaction->member->id
                . "attempted-account-session"
            ),
            $object,
            DiscordProperties::SYSTEM_REFRESH_TIME
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

    public static function getAccountObject(Interaction|Message|null $interaction): object
    {
        $account = new Account();

        if ($interaction !== null) {
            $account->getSession()->setCustomKey("discord", $interaction->member->id);
        }
        return $account;
    }

    public static function findAccountFromSession(Interaction|Message|null $interaction): ?object
    {
        $account = self::getAccountObject($interaction);
        $method = $account->getSession()->find(false);

        if ($method->isPositiveOutcome()) {
            return $method->getObject();
        } else {
            return null;
        }
    }

    public static function my_account(DiscordBot     $bot,
                                      ?Interaction   $interaction,
                                      MessageBuilder $messageBuilder): MessageBuilder
    {
        if (false) {
            $account = self::getAccountObject($interaction);
            $productGiveaway = $account->getProductGiveaway();
            $currentGiveawayOutcome = $productGiveaway->getCurrent(null, 1, "14 days");
            $currentGiveaway = $currentGiveawayOutcome->getObject();

            if ($currentGiveaway !== null) { // Check if current giveaway exists
                $embed = new Embed($bot->discord);
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
                        $announcementEmbed = new Embed($bot->discord);
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
                        $channel = $bot->discord->getChannel(0);

                        if ($channel !== null
                            && $channel->allowText()) {
                            $channel->sendMessage($announcement);
                        }
                    }
                }
                $messageBuilder->addEmbed($embed);
            }
        }
        return $messageBuilder;
    }

    private static function loadProduct(DiscordBot $bot,
                                        mixed      $account,
                                        object     $product): MessageBuilder
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
        $downloadToken = $account->getDownloads()->create(
            $productID,
            2,
            false,
            null,
            null
        );
        $downloadURLs = $downloadToken != null && $downloadToken->isPositiveOutcome()
            ? array($product->download_placeholder . "?userToken=" . $downloadToken->getObject())
            : null;

        if (empty($downloadURLs)) {
            $downloadURLs = $account->getProduct()->findIdentifications(
                $product,
                array(
                    AccountAccounts::SPIGOTMC_URL,
                    AccountAccounts::BUILTBYBIT_URL,
                    AccountAccounts::POLYMART_URL,
                )
            );
        }
        $hasTiers = sizeof($product->tiers->paid) > 1;
        $paidTiers = $product->tiers->paid;
        $tier = array_shift($paidTiers);
        $price = $isFree
            ? null
            : ($hasTiers ? "Starting from " : "") . $tier->price . " " . $tier->currency;

        // Separator

        $embed = new Embed($bot->discord);

        if ($product->color !== null) {
            $embed->setColor($product->color);
        }

        if (!empty($downloadURLs)) {
            $actionRow = ActionRow::new();

            foreach ($downloadURLs as $object) {
                $isString = is_string($object);

                $button = Button::new(Button::STYLE_LINK)
                    ->setLabel(
                        $isString ? "Download" : "Download (" . explode(" ", $object->accepted_account->name, 2)[0] . ")"
                    )->setUrl(
                        $isString ? $object : $object->product_url
                    );
                $actionRow->addComponent($button);
            }
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
        } else {
            if ($product->download_note !== null) {
                if ($price !== null) {
                    $embed->setFooter($price . " (" . DiscordSyntax::htmlToDiscord($product->download_note) . ")");
                } else {
                    $embed->setFooter(DiscordSyntax::htmlToDiscord($product->download_note));
                }
            } else {
                $embed->setFooter($price);
            }
        }

        $embed->setAuthor(
            strip_tags($product->name),
            $product->sub_image
        );
        if ($product->latest_version !== null) {
            $embed->setTitle(strip_tags($product->latest_version->prefix)
                . ($product->latest_version?->version !== null
                    ? " " . $product->latest_version->version
                    : "")
                . ($product->latest_version?->suffix !== null
                    ? " " . strip_tags($product->latest_version->suffix)
                    : ""));
        }
        $embed->setImage($product->image);
        //$activeCustomers = $isFree ? null : ($product->registered_buyers === 0 ? null : $product->registered_buyers);
        $legalInformation = $product->legal_information !== null
            ? "[By purchasing/downloading, you accept these linked terms](" . $product->legal_information . ")"
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
                            $embed = new Embed($bot->discord);
                            $embed->setTitle(strip_tags($compatibleProduct->name));

                            if ($compatibleProduct->color !== null) {
                                $embed->setColor($compatibleProduct->color);
                            }
                            $embed->setDescription(DiscordSyntax::htmlToDiscord($compatibleProduct->description));
                            $embed->setAuthor(
                                strip_tags($product->compatibility_description),
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
                    $embed = new Embed($bot->discord);

                    if ($family !== null) {
                        $embed->setTitle(strip_tags($family));
                    }
                    foreach ($divisions as $division) {
                        $embed->addFieldValues(
                            DiscordSyntax::htmlToDiscord($division->name),
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
                $select->setListener($bot->utilities->twoArgumentsFunction(
                    function (Interaction $interaction, Collection $options)
                    use ($bot, $productDivisions) {
                        $bot->utilities->acknowledgeMessage(
                            $interaction,
                            function () use ($bot, $productDivisions, $options) {
                                $reply = MessageBuilder::new();
                                $embed = new Embed($bot->discord);
                                $division = $productDivisions[$options[0]->getValue()];

                                if ($division->has_title) {
                                    $embed->setTitle(strip_tags($division->title));
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
                    }
                ), $bot->discord);
            }
        }

        // Separator

        $productButtons = $hasPurchased
            ? $product->buttons->post_purchase
            : $product->buttons->pre_purchase;

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
                        $button->setListener($bot->utilities->oneArgumentFunction(
                            function (Interaction $interaction)
                            use ($bot, $actionRow, $buttonObj) {
                                $bot->utilities->acknowledgeMessage(
                                    $interaction,
                                    $bot->utilities->zeroArgumentFunction(
                                        function () use ($buttonObj) {
                                            return MessageBuilder::new()->setContent($buttonObj->url);
                                        }
                                    ),
                                    true
                                );
                            }
                        ), $bot->discord);
                        $actionRow->addComponent($button);
                    }
                }
            }
            $messageBuilder->addComponent($actionRow);
        }

        // Separator

        $productCards = $hasPurchased
            ? $product->cards->post_purchase
            : $product->cards->pre_purchase;

        if (!empty($productCards)) {
            foreach ($productCards as $card) {
                $embed = new Embed($bot->discord);
                $embed->setAuthor(
                    strip_tags($card->name),
                    $card->image,
                    $card->url
                );
                if ($product->color !== null) {
                    $embed->setColor($product->color);
                }
                $messageBuilder->addEmbed($embed);
            }
        }
        return $messageBuilder;
    }

    public static function toggle_settings(DiscordBot     $bot,
                                           ?Interaction   $interaction,
                                           MessageBuilder $messageBuilder): MessageBuilder
    {
        $account = self::findAccountFromSession($interaction);

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
            $messageBuilder = $bot->persistentMessages->get($interaction, "0-register_or_log_in");
        }
        return $messageBuilder;
    }

    public static function connect_account(DiscordBot     $bot,
                                           ?Interaction   $interaction,
                                           MessageBuilder $messageBuilder,
                                           bool           $addIfEmpty = true): MessageBuilder
    {
        $account = self::findAccountFromSession($interaction);

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
            $messageBuilder = $bot->persistentMessages->get($interaction, "0-register_or_log_in");
        }
        return $messageBuilder;
    }

    public static function disconnect_account(DiscordBot     $bot,
                                              ?Interaction   $interaction,
                                              MessageBuilder $messageBuilder): MessageBuilder
    {
        return self::connect_account($bot, $interaction, $messageBuilder, false);
    }

    public static function logged_in(DiscordBot     $bot,
                                     ?Interaction   $interaction,
                                     MessageBuilder $messageBuilder): MessageBuilder
    {
        $account = self::findAccountFromSession($interaction);

        if ($account !== null) {
            $name = $account->getDetail("name");
            $embed = new Embed($bot->discord);
            $embed->setAuthor(
                $name,
                get_minecraft_head_image($name, 64)
            );
            $embed->setTitle(
                "Welcome back!"
                . "\n\nDon't forget to connect any accounts you have so we can provide you with your purchases."
            );
            $embed->setFooter("Accounts who leave the Discord server are deleted!");
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
                            get_full_date($history[$max - 1]->creation_date)
                            . " - "
                            . get_full_date($history[$counter]->creation_date),
                            $i
                        ));
                    }
                    $select->setListener($bot->utilities->twoArgumentsFunction(
                        function (Interaction $interaction, Collection $options)
                        use ($size, $bot, $select, $history, $limit) {
                            $bot->utilities->acknowledgeMessage(
                                $interaction,
                                function () use ($size, $bot, $interaction, $options, $history, $limit) {
                                    $account = self::findAccountFromSession($interaction);

                                    if ($account !== null) {
                                        $count = $options[0]->getValue();
                                        $messageBuilder = MessageBuilder::new();

                                        $counter = $count * $limit;
                                        $max = min($counter + $limit, $size);
                                        $divisor = 0;
                                        $embed = new Embed($bot->discord);
                                        $embed->setTitle("Account History");
                                        $embed->setDescription(
                                            get_full_date($history[$max - 1]->creation_date)
                                            . " - "
                                            . get_full_date($history[$counter]->creation_date)
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
                                        return $bot->persistentMessages->get($interaction, "0-register_or_log_in");
                                    }
                                },
                                true
                            );
                        }
                    ), $bot->discord);
                    $messageBuilder->addComponent($select);
                }
            }
        }
        return $messageBuilder;
    }

}