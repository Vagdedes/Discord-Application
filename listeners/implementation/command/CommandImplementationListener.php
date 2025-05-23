<?php

use Discord\Builders\Components\Option;
use Discord\Builders\Components\SelectMenu;
use Discord\Builders\MessageBuilder;
use Discord\Helpers\Collection;
use Discord\Parts\Channel\Message;
use Discord\Parts\Embed\Embed;
use Discord\Parts\Interactions\Interaction;

class CommandImplementationListener
{

    private const COLOUR_ROLES = array(
        "red" => 1276174603055796305,
        "blue" => 1276174656713396305,
        "green" => 1276174660870082641,
        "orange" => 1276174700560777297,
        "yellow" => 1276174704255959141,
        "purple" => 1276174743724101666,
        "gray" => 1276175693444677655
    );

    private const OBJECT_NOT_FOUND = "Object not found.",
        ACCOUNT_NOT_FOUND = "Account not found.",
        LOGGED_IN = "You must be logged in.";

    private static function printResult(mixed $reply): string
    {
        return ($reply->isPositiveOutcome() ? "true" : "false")
            . " | " . $reply->getMessage()
            . " | " . json_encode($reply->getObject());
    }

    public static function account_info(DiscordBot          $bot,
                                        Interaction|Message $interaction,
                                        object              $command): void
    {
        $message = new MessageBuilder();
        $account = new Account();
        $account->getSession()->setCustomKey("discord", $interaction->data?->resolved?->users?->first()?->id);
        $object = $account->getSession()->getLastKnown();

        if ($object !== null) {
            $account = $account->transform($object->account_id);

            if ($account->exists()) {
                $arguments = $interaction->data->options->toArray();
                $data = $arguments["data"]["value"] ?? null;

                if ($data !== null) {
                    $data = strtolower($data);
                    $schemas = get_sql_database_schemas();

                    if (!empty($schemas)) {
                        $nullColumnsKey = "NULL: ";
                        $embedCount = 0;
                        $embedLength = strlen($nullColumnsKey);

                        foreach ($schemas as $schema) {
                            $tables = get_sql_database_tables($schema);

                            if (!empty($tables)) {
                                foreach ($tables as $table) {
                                    $table = $schema . "." . $table;

                                    if (str_contains(strtolower($table), $data)) {
                                        $query = get_sql_query(
                                            $table,
                                            null,
                                            array(
                                                array("account_id", $account->getDetail("id")),
                                            ),
                                            array(
                                                "DESC",
                                                "id"
                                            ),
                                            DiscordInheritedLimits::MAX_FIELDS_PER_EMBED
                                        );

                                        if (!empty($query)) {
                                            $embed = new Embed($bot->discord);
                                            $length = strlen($table);

                                            if ($embedLength + $length <= DiscordInheritedLimits::EMBED_COLLECTIVE_LENGTH_LIMIT) {
                                                $embed->setTitle($table);
                                                $embedLength += $length;
                                            } else {
                                                break 2;
                                            }
                                            foreach ($query as $row) {
                                                $index = $row->id;
                                                unset($row->id);
                                                unset($row->account_id);
                                                $rowString = "";
                                                $nullKeys = array();
                                                $length = strlen($index);

                                                if ($embedLength + $length <= DiscordInheritedLimits::EMBED_COLLECTIVE_LENGTH_LIMIT) {
                                                    $embedLength += $length;
                                                } else {
                                                    break 3;
                                                }
                                                foreach ($row as $key => $value) {
                                                    if (empty($value)) {
                                                        $length = strlen($key);

                                                        if ($embedLength + $length <= DiscordInheritedLimits::EMBED_COLLECTIVE_LENGTH_LIMIT) {
                                                            $nullKeys[] = $key;
                                                            $embedLength += $length;
                                                        } else {
                                                            break 4;
                                                        }
                                                    } else {
                                                        $string = "$key: $value\n";
                                                        $length = strlen($string);

                                                        if ($embedLength + $length <= DiscordInheritedLimits::EMBED_COLLECTIVE_LENGTH_LIMIT) {
                                                            $rowString .= $string;
                                                            $embedLength += $length;
                                                        } else {
                                                            break 4;
                                                        }
                                                    }
                                                }
                                                if (!empty($nullKeys)) {
                                                    $rowString .= $nullColumnsKey . implode(", ", $nullKeys);
                                                }
                                                $embed->addFieldValues(
                                                    $index,
                                                    DiscordSyntax::HEAVY_CODE_BLOCK
                                                    . str_replace(DiscordSyntax::HEAVY_CODE_BLOCK, "", $rowString)
                                                    . DiscordSyntax::HEAVY_CODE_BLOCK,
                                                    false
                                                );
                                            }
                                            $message->addEmbed($embed);
                                            $embedCount++;

                                            if ($embedCount === DiscordInheritedLimits::MAX_EMBEDS_PER_MESSAGE) {
                                                break 2;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                $message->setContent(
                    "ID: " . $account->getDetail("id") . "\n"
                    . "URL: https://www.idealistic.ai/contents/?path=account/panel&platform=1&id="
                    . $account->getDetail("email_address")
                );
            } else {
                $message->setContent(self::ACCOUNT_NOT_FOUND);
            }
        } else {
            $message->setContent(self::OBJECT_NOT_FOUND);
        }
        $bot->utilities->acknowledgeCommandMessage(
            $interaction,
            $message,
            true
        );
    }

    public static function give_product(DiscordBot          $bot,
                                        Interaction|Message $interaction,
                                        object              $command): void
    {
        $message = new MessageBuilder();
        $account = new Account();
        $account->getSession()->setCustomKey("discord", $interaction->data?->resolved?->users?->first()?->id);
        $object = $account->getSession()->getLastKnown();

        if ($object !== null) {
            $account = $account->transform($object->account_id);

            if ($account->exists()) {
                $arguments = $interaction->data->options->toArray();
                $reply = $account->getPurchases()->add(
                    $arguments["product-id"]["value"] ?? null
                );
                $message->setContent(self::printResult($reply));
            } else {
                $message->setContent(self::ACCOUNT_NOT_FOUND);
            }
        } else {
            $message->setContent(self::OBJECT_NOT_FOUND);
        }
        $bot->utilities->acknowledgeCommandMessage(
            $interaction,
            $message,
            true
        );
    }

    public static function remove_product(DiscordBot          $bot,
                                          Interaction|Message $interaction,
                                          object              $command): void
    {
        $message = new MessageBuilder();
        $account = new Account();
        $account->getSession()->setCustomKey("discord", $interaction->data?->resolved?->users?->first()?->id);
        $object = $account->getSession()->getLastKnown();

        if ($object !== null) {
            $account = $account->transform($object->account_id);

            if ($account->exists()) {
                $arguments = $interaction->data->options->toArray();
                $reply = $account->getPurchases()->remove(
                    $arguments["product-id"]["value"] ?? null
                );
                $message->setContent(self::printResult($reply));
            } else {
                $message->setContent(self::ACCOUNT_NOT_FOUND);
            }
        } else {
            $message->setContent(self::OBJECT_NOT_FOUND);
        }
        $bot->utilities->acknowledgeCommandMessage(
            $interaction,
            $message,
            true
        );
    }

    public static function exchange_product(DiscordBot          $bot,
                                            Interaction|Message $interaction,
                                            object              $command): void
    {
        $message = new MessageBuilder();
        $account = new Account();
        $account->getSession()->setCustomKey("discord", $interaction->data?->resolved?->users?->first()?->id);
        $object = $account->getSession()->getLastKnown();

        if ($object !== null) {
            $account = $account->transform($object->account_id);

            if ($account->exists()) {
                $arguments = $interaction->data->options->toArray();
                $reply = $account->getPurchases()->exchange(
                    $arguments["product-id"]["value"] ?? null,
                    null,
                    $arguments["exchange-product-id"]["value"] ?? null
                );
                $message->setContent(self::printResult($reply));
            } else {
                $message->setContent(self::ACCOUNT_NOT_FOUND);
            }
        } else {
            $message->setContent(self::OBJECT_NOT_FOUND);
        }
        $bot->utilities->acknowledgeCommandMessage(
            $interaction,
            $message,
            true
        );
    }

    public static function delete_account(DiscordBot          $bot,
                                          Interaction|Message $interaction,
                                          object              $command): void
    {
        $message = new MessageBuilder();
        $account = new Account();
        $account->getSession()->setCustomKey("discord", $interaction->data?->resolved?->users?->first()?->id);
        $object = $account->getSession()->getLastKnown();

        if ($object !== null) {
            $account = $account->transform($object->account_id);

            if ($account->exists()) {
                $arguments = $interaction->data->options->toArray();
                $reply = $account->getActions()->deleteAccount(
                    $arguments["permanent"]["value"] ?? null
                );
                $message->setContent(self::printResult($reply));
            } else {
                $message->setContent(self::ACCOUNT_NOT_FOUND);
            }
        } else {
            $message->setContent(self::OBJECT_NOT_FOUND);
        }
        $bot->utilities->acknowledgeCommandMessage(
            $interaction,
            $message,
            true
        );
    }

    public static function queue_paypal_transaction(DiscordBot          $bot,
                                                    Interaction|Message $interaction,
                                                    object              $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $bot->utilities->acknowledgeCommandMessage(
            $interaction,
            MessageBuilder::new()->setContent(
                strval(queue_paypal_transaction($arguments["transaction-id"]["value"] ?? null))
            ),
            true
        );
    }

    public static function fail_paypal_transaction(DiscordBot          $bot,
                                                   Interaction|Message $interaction,
                                                   object              $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $bot->utilities->acknowledgeCommandMessage(
            $interaction,
            MessageBuilder::new()->setContent(
                strval(process_failed_paypal_transaction($arguments["transaction-id"]["value"] ?? null))
            ),
            true
        );
    }

    public static function suspend_paypal_transactions(DiscordBot          $bot,
                                                       Interaction|Message $interaction,
                                                       object              $command): void
    {
        $message = new MessageBuilder();
        $account = new Account();
        $account->getSession()->setCustomKey("discord", $interaction->data?->resolved?->users?->first()?->id);
        $object = $account->getSession()->getLastKnown();

        if ($object !== null) {
            $account = $account->transform($object->account_id);

            if ($account->exists()) {
                $arguments = $interaction->data->options->toArray();
                $transactions = $account->getTransactions()->getSuccessful(PaymentProcessor::PAYPAL);

                if (empty($transactions)) {
                    $message->setContent("No transactions available.");
                } else {
                    $reason = $arguments["reason"]["value"] ?? null;
                    $coverFees = $arguments["cover-fees"]["value"] ?? null;
                    $success = 0;
                    $failure = 0;

                    foreach ($transactions as $transaction) {
                        if (suspend_paypal_transaction(
                            $transaction->id,
                            $reason,
                            $coverFees
                        )) {
                            $success++;
                        } else {
                            $failure++;
                        }
                    }
                    $message->setContent(
                        "Success: $success | Failure: $failure | Total: " . sizeof($transactions)
                    );
                }
            } else {
                $message->setContent(self::ACCOUNT_NOT_FOUND);
            }
        } else {
            $message->setContent(self::OBJECT_NOT_FOUND);
        }
        $bot->utilities->acknowledgeCommandMessage(
            $interaction,
            $message,
            true
        );
    }

    public static function account_functionality(DiscordBot          $bot,
                                                 Interaction|Message $interaction,
                                                 object              $command): void
    {
        $staffAccount = AccountMessageCreationListener::findAccountFromSession($interaction);
        $message = new MessageBuilder();

        if ($staffAccount === null) {
            $message->setContent(self::LOGGED_IN);
        } else {
            $account = new Account();
            $account->getSession()->setCustomKey("discord", $interaction->data?->resolved?->users?->first()?->id);
            $object = $account->getSession()->getLastKnown();

            if ($object !== null) {
                $account = $account->transform($object->account_id);

                if ($account->exists()) {
                    $functionalities = $account->getFunctionality()->getAvailable(
                        DiscordInheritedLimits::MAX_CHOICES_PER_SELECTION
                    );

                    if (empty($functionalities)) {
                        $message->setContent("No functionalities available.");
                    } else {
                        $arguments = $interaction->data->options->toArray();
                        $block = $arguments["block"]["value"] ?? null;
                        $reason = $arguments["reason"]["value"] ?? null;
                        $duration = $arguments["duration"]["value"] ?? null;
                        $select = SelectMenu::new();
                        $select->setMinValues(1);
                        $select->setMaxValues(1);
                        $select->setPlaceholder("Select a functionality.");

                        foreach ($functionalities as $id => $functionality) {
                            $option = Option::new(substr($functionality, 0, 100), $id);
                            $select->addOption($option);
                        }

                        $select->setListener($bot->utilities->twoArgumentsFunction(
                            function (Interaction|Message $interaction, Collection $options)
                            use ($block, $reason, $duration, $bot, $account, $staffAccount) {
                                $bot->utilities->acknowledgeMessage(
                                    $interaction,
                                    $bot->utilities->zeroArgumentFunction(
                                        function () use ($block, $reason, $duration, $account, $staffAccount, $options) {
                                            $functionality = $options[0]->getValue();

                                            if ($block) {
                                                $reply = $staffAccount->getFunctionality()->executeAction(
                                                    $account->getDetail("id"),
                                                    $functionality,
                                                    $reason,
                                                    !empty($duration) ? $duration : null,
                                                );
                                            } else {
                                                $reply = $staffAccount->getFunctionality()->cancelAction(
                                                    $account->getDetail("id"),
                                                    $functionality,
                                                    $reason
                                                );
                                            }
                                            return MessageBuilder::new()->setContent(self::printResult($reply));
                                        }
                                    ),
                                    true
                                );
                            }
                        ), $bot->discord, true);
                        $message->addComponent($select);
                    }
                } else {
                    $message->setContent(self::ACCOUNT_NOT_FOUND);
                }
            } else {
                $message->setContent(self::OBJECT_NOT_FOUND);
            }
            $bot->utilities->acknowledgeCommandMessage(
                $interaction,
                $message,
                true
            );
        }
    }

    public static function account_moderation(DiscordBot          $bot,
                                              Interaction|Message $interaction,
                                              object              $command): void
    {
        $staffAccount = AccountMessageCreationListener::findAccountFromSession($interaction);
        $message = new MessageBuilder();

        if ($staffAccount === null) {
            $message->setContent(self::LOGGED_IN);
        } else {
            $account = new Account();
            $account->getSession()->setCustomKey("discord", $interaction->data?->resolved?->users?->first()?->id);
            $object = $account->getSession()->getLastKnown();

            if ($object !== null) {
                $account = $account->transform($object->account_id);

                if ($account->exists()) {
                    $moderations = $account->getModerations()->getAvailable(
                        DiscordInheritedLimits::MAX_CHOICES_PER_SELECTION
                    );

                    if (empty($moderations)) {
                        $message->setContent("No moderations available.");
                    } else {
                        $arguments = $interaction->data->options->toArray();
                        $punish = $arguments["punish"]["value"] ?? null;
                        $reason = $arguments["reason"]["value"] ?? null;
                        $duration = $arguments["duration"]["value"] ?? null;
                        $select = SelectMenu::new();
                        $select->setMinValues(1);
                        $select->setMaxValues(1);
                        $select->setPlaceholder("Select a functionality.");

                        foreach ($moderations as $id => $moderation) {
                            $option = Option::new(substr($moderation, 0, 100), $id);
                            $select->addOption($option);
                        }

                        $select->setListener($bot->utilities->twoArgumentsFunction(
                            function (Interaction|Message $interaction, Collection $options)
                            use ($punish, $reason, $duration, $bot, $account, $staffAccount) {
                                $bot->utilities->acknowledgeMessage(
                                    $interaction,
                                    $bot->utilities->zeroArgumentFunction(
                                        function () use ($punish, $reason, $duration, $account, $staffAccount, $options) {
                                            $functionality = $options[0]->getValue();

                                            if ($punish) {
                                                $reply = $staffAccount->getModerations()->executeAction(
                                                    $account->getDetail("id"),
                                                    $functionality,
                                                    $reason,
                                                    !empty($duration) ? $duration : null,
                                                );
                                            } else {
                                                $reply = $staffAccount->getModerations()->cancelAction(
                                                    $account->getDetail("id"),
                                                    $functionality,
                                                    $reason
                                                );
                                            }
                                            return MessageBuilder::new()->setContent(self::printResult($reply));
                                        }
                                    ),
                                    true
                                );
                            }
                        ), $bot->discord, true);
                        $message->addComponent($select);
                    }
                } else {
                    $message->setContent(self::ACCOUNT_NOT_FOUND);
                }
            } else {
                $message->setContent(self::OBJECT_NOT_FOUND);
            }
            $bot->utilities->acknowledgeCommandMessage(
                $interaction,
                $message,
                true
            );
        }
    }

    public static function manage_platform_user(DiscordBot          $bot,
                                                Interaction|Message $interaction,
                                                object              $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $gameCloudUser = new GameCloudUser(
            $arguments["platform-id"]["value"] ?? null,
            $arguments["license-id"]["value"] ?? null
        );
        $reason = $arguments["reason"]["value"] ?? null;
        $duration = $arguments["duration"]["value"] ?? null;
        $product = $arguments["product-id"]["value"] ?? null;
        $type = $arguments["type"]["value"] ?? null;
        $ipAddress = $arguments["ip-address"]["value"] ?? null;

        if ($arguments["add"]["value"] ?? false) {
            $message = strval($gameCloudUser->getVerification()->addLicenseManagement(
                $product,
                $type,
                !empty($reason) ? $reason : null,
                !empty($duration) ? get_future_date($duration) : null,
                $ipAddress
            ));
        } else {
            $message = strval($gameCloudUser->getVerification()->removeLicenseManagement(
                $product,
                $type
            ));
        }
        $bot->utilities->acknowledgeCommandMessage(
            $interaction,
            MessageBuilder::new()->setContent($message),
            true
        );
    }

    public static function financial_input(DiscordBot          $bot,
                                           Interaction|Message $interaction,
                                           object              $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $year = $arguments["year"]["value"] ?? null;
        $month = $arguments["month"]["value"] ?? null;
        $bot->utilities->acknowledgeCommandMessage(
            $interaction,
            MessageBuilder::new()->setContent("Please wait..."),
            true
        )->done(function () use ($interaction, $year, $month, $bot) {
            $results = get_financial_input($year, $month);

            if (!empty($results)) {
                $results = $results["total"] ?? null;

                if ($results !== null) {
                    if (is_object($results)) {
                        $message = MessageBuilder::new()->setContent("Completed");
                        $embed = new Embed($bot->discord);

                        foreach ($results as $key => $value) {
                            $embed->addFieldValues(
                                is_string($key) ? $key : json_encode($key),
                                is_string($value) ? $value : json_encode($value),
                                false
                            );
                        }
                        $message->addEmbed($embed);
                    } else {
                        $message = MessageBuilder::new()->setContent("No information found. (3)");
                    }
                } else {
                    $message = MessageBuilder::new()->setContent("No information found. (2)");
                }
            } else {
                $message = MessageBuilder::new()->setContent("No information found. (1)");
            }
            $interaction->updateOriginalResponse($message);
        });
    }

    public static function color_red(DiscordBot          $bot,
                                     Interaction|Message $interaction,
                                     object              $command): void
    {
        foreach (self::COLOUR_ROLES as $role => $id) {
            if ($role == "red") {
                $interaction->member->addRole($id);
            } else {
                $interaction->member->removeRole($id);
            }
        }
        $interaction->react("👍");
    }

    public static function color_blue(DiscordBot          $bot,
                                      Interaction|Message $interaction,
                                      object              $command): void
    {
        foreach (self::COLOUR_ROLES as $role => $id) {
            if ($role == "blue") {
                $interaction->member->addRole($id);
            } else {
                $interaction->member->removeRole($id);
            }
        }
        $interaction->react("👍");
    }

    public static function color_green(DiscordBot          $bot,
                                       Interaction|Message $interaction,
                                       object              $command): void
    {
        foreach (self::COLOUR_ROLES as $role => $id) {
            if ($role == "green") {
                $interaction->member->addRole($id);
            } else {
                $interaction->member->removeRole($id);
            }
        }
        $interaction->react("👍");
    }

    public static function color_yellow(DiscordBot          $bot,
                                        Interaction|Message $interaction,
                                        object              $command): void
    {
        foreach (self::COLOUR_ROLES as $role => $id) {
            if ($role == "yellow") {
                $interaction->member->addRole($id);
            } else {
                $interaction->member->removeRole($id);
            }
        }
        $interaction->react("👍");
    }

    public static function color_orange(DiscordBot          $bot,
                                        Interaction|Message $interaction,
                                        object              $command): void
    {
        foreach (self::COLOUR_ROLES as $role => $id) {
            if ($role == "orange") {
                $interaction->member->addRole($id);
            } else {
                $interaction->member->removeRole($id);
            }
        }
        $interaction->react("👍");
    }

    public static function color_purple(DiscordBot          $bot,
                                        Interaction|Message $interaction,
                                        object              $command): void
    {
        foreach (self::COLOUR_ROLES as $role => $id) {
            if ($role == "purple") {
                $interaction->member->addRole($id);
            } else {
                $interaction->member->removeRole($id);
            }
        }
        $interaction->react("👍");
    }

    public static function color_gray(DiscordBot          $bot,
                                      Interaction|Message $interaction,
                                      object              $command): void
    {
        foreach (self::COLOUR_ROLES as $role => $id) {
            if ($role == "gray") {
                $interaction->member->addRole($id);
            } else {
                $interaction->member->removeRole($id);
            }
        }
        $interaction->react("👍");
    }

    public static function embed_reply(DiscordBot          $bot,
                                       Interaction|Message $interaction,
                                       object              $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $embed = $arguments["embed"]["value"] ?? null;
        $builder = $bot->persistentMessages->get(
            $interaction,
            $embed
        );

        if ($builder !== null) {
            $bot->utilities->acknowledgeCommandMessage(
                $interaction,
                $builder->setContent(
                    $arguments["reply"]["value"] ?? null
                ),
                false
            );
        } else {
            $bot->utilities->acknowledgeCommandMessage(
                $interaction,
                MessageBuilder::new()->setContent("Embed not found for bot '" . $bot->botID . "': " . $embed),
                true
            );
        }
    }

    public static function set_cloud_product(DiscordBot          $bot,
                                             Interaction|Message $interaction,
                                             object              $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $gameCloudUser = new GameCloudUser(
            $arguments["platform-id"]["value"] ?? null,
            $arguments["license-id"]["value"] ?? null
        );
        $email = $arguments["email-address"]["value"] ?? null;
        $data = $arguments["data-directory"]["value"] ?? null;
        $trueFalse = $arguments["true-or-false"]["value"] ?? null;
        $expiration = $arguments["expiration-date"]["value"] ?? null;
        $justification = $arguments["justification"]["value"] ?? null;

        $bot->utilities->acknowledgeCommandMessage(
            $interaction,
            MessageBuilder::new()->setContent(
                strval($gameCloudUser->getPurchases()->addToDatabase(
                    $email,
                    $data,
                    $trueFalse,
                    $expiration,
                    $justification
                ))
            ),
            true
        );
    }

    public static function transfer_from_spigotmc(DiscordBot          $bot,
                                                  Interaction|Message $interaction,
                                                  object              $command): void
    {
        $bot->utilities->acknowledgeCommandMessage(
            $interaction,
            MessageBuilder::new()->setContent("Please wait..."),
            true
        )->done(function () use ($interaction, $bot) {
            try {
                $arguments = $interaction->data->options->toArray();
                $email = $arguments["paypal-email-address"]["value"] ?? null;

                if ($email !== null) {
                    $emails = explode(",", $email);
                    $transactions = array();
                    $threshold = 19.99;
                    $doubleThreshold = $threshold * 2.0;
                    $offerThreshold = $doubleThreshold * 0.7;
                    $thresholdWin = ceil($doubleThreshold - $offerThreshold);
                    $amount = 0;
                    $amountPerYear = 0;
                    $emailsAnalyzed = array();
                    $builder = MessageBuilder::new();
                    $embed = new Embed($bot->discord);
                    $embeds = 0;

                    foreach ($emails as $email) {
                        $email = trim(strtolower($email));

                        if (!is_email($email)) {
                            $interaction->updateOriginalResponse(
                                MessageBuilder::new()->setContent("Invalid email address/es provided.")
                            );
                            return;
                        }
                        if (in_array($email, $emailsAnalyzed)) {
                            continue;
                        } else {
                            $emailsAnalyzed[] = $email;
                        }
                        $find = find_paypal_transactions_by_data_pair(
                            array(
                                "EMAIL" => $email,
                            )
                        );

                        if (!empty($find)) {
                            foreach ($find as $transaction) {
                                $transactions[] = $transaction;
                            }
                        }
                    }

                    if (!empty($transactions)) {
                        $oldestDate = null;
                        $failedTransactions = get_failed_paypal_transactions(10_000);

                        foreach ($transactions as $transaction) {
                            if (!isset($transaction->TRANSACTIONID)) {
                                continue;
                            }
                            if (in_array($transaction->TRANSACTIONID, $failedTransactions)) {
                                continue;
                            }
                            if (isset($transaction->TIMESTAMP)
                                && isset($transaction->AMT)
                                && isset($transaction->L_NAME0)) {
                                $date = reformat_date($transaction->TIMESTAMP);

                                if ($oldestDate === null || $date < $oldestDate) {
                                    $oldestDate = $date;
                                }
                                $amount += $transaction->AMT;

                                if ($embeds < DiscordInheritedLimits::MAX_FIELDS_PER_EMBED) {
                                    $embed->addFieldValues(
                                        $transaction->L_NAME0,
                                        cut_decimal($transaction->AMT, 2)
                                        . " | " . reformat_date($transaction->TIMESTAMP)
                                    );
                                    $embeds++;
                                }
                            }
                        }
                        if (!empty($embed->fields->first())) {
                            $builder->addEmbed($embed);
                        }

                        if ($oldestDate !== null) {
                            $currentDate = get_current_date();
                            $secondsInAYear = 31_536_000;
                            $timePassed = strtotime($currentDate) - strtotime($oldestDate);
                            $yearsPassed = ($timePassed / $secondsInAYear) + 1.0;
                            $amountPerYear = $amount / $yearsPassed;

                            if ($amountPerYear >= $threshold) {
                                if ($amountPerYear >= $offerThreshold) {
                                    $interaction->updateOriginalResponse(
                                        $builder->setContent(
                                            "You can transfer the Java and Bedrock editions from the SpigotMC platform. "
                                            . "You have paid us about ``" . cut_decimal($amountPerYear, 2) . " EUR`` per year. "
                                            . "Create a ticket and provide us with your (1) paypal email address/es"
                                            . " and (2) [BuiltByBit](https://builtbybit.com) username."
                                        )
                                    );
                                } else {
                                    $interaction->updateOriginalResponse(
                                        $builder->setContent(
                                            "You can transfer the Java **or** the Bedrock edition from the SpigotMC platform. "
                                            . "You have paid us about ``" . cut_decimal($amountPerYear, 2) . " EUR`` per year. "
                                            . "Optionally, you can pay ``" . cut_decimal($offerThreshold - $amountPerYear, 2)
                                            . " EUR`` to transfer both the Java and Bedrock editions (effectively saving ``" . $thresholdWin . " EUR``). "
                                            . "Create a ticket and provide us with your (1) paypal email address/es"
                                            . " and (2) [BuiltByBit](https://builtbybit.com) username."
                                        )
                                    );
                                }
                                return;
                            }
                        }
                    }

                    $owed = ceil($threshold - $amountPerYear);
                    $interaction->updateOriginalResponse(
                        $builder->setContent(
                            "Hi " . $interaction->user->username . "! Try to not be pessimistic about the following information, our support can find a specific solution for you. "
                            . " You can pay ``"
                            . $owed
                            . " EUR`` to transfer from the SpigotMC platform the Java **or** the Bedrock edition."
                            . " Optionally, you can pay ``" . ceil($offerThreshold - $amountPerYear) . " EUR`` to transfer both the Java and Bedrock editions"
                            . " (effectively saving ``" . $thresholdWin . " EUR``). "
                            . ($amountPerYear > 0.0
                                ? " Fortunately, you have already paid ``" . floor($amountPerYear) . " EUR`` of this amount over the years. "
                                . "Create a ticket and provide us with your (1) paypal email address/es"
                                . " and (2) [BuiltByBit](https://builtbybit.com) username."
                                : " No transactions were found, meaning (1) you have not paid any amount or (2) no valid email address was provided or (3) your transactions are too old and would not suffice anyway.")
                        )
                    );
                } else {
                    $interaction->updateOriginalResponse(
                        MessageBuilder::new()->setContent("No single email address provided.")
                    );
                }
            } catch (Throwable $e) {
                $interaction->updateOriginalResponse(
                    MessageBuilder::new()->setContent($e->getMessage()),
                );
            }
        });
    }

}