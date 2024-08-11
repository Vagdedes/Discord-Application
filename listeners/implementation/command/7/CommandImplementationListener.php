<?php

use Discord\Builders\Components\Option;
use Discord\Builders\Components\SelectMenu;
use Discord\Builders\MessageBuilder;
use Discord\Helpers\Collection;
use Discord\Parts\Embed\Embed;
use Discord\Parts\Interactions\Interaction;

class CommandImplementationListener
{

    private const OBJECT_NOT_FOUND = "Object not found.",
        ACCOUNT_NOT_FOUND = "Account not found.",
        LOGGED_IN = "You must be logged in.";

    private static function printResult(mixed $reply): string
    {
        return ($reply->isPositiveOutcome() ? "true" : "false")
            . " | " . $reply->getMessage()
            . " | " . json_encode($reply->getObject());
    }

    public static function account_info(DiscordPlan $plan,
                                        Interaction $interaction,
                                        object      $command): void
    {
        $message = new MessageBuilder();
        $account = new Account();
        $account->getSession()->setCustomKey("discord", $interaction->data?->resolved?->users?->first()?->id);
        $object = $account->getSession()->getLastKnown();

        if ($object !== null) {
            $account = $account->transform($object->account_id);

            if ($account->exists()) {
                $arguments = $interaction->data->options->toArray();
                $data = $arguments["data"]["value"];

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
                                            $embed = new Embed($plan->bot->discord);
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
        $plan->utilities->acknowledgeCommandMessage(
            $interaction,
            $message,
            true
        );
    }

    public static function give_product(DiscordPlan $plan,
                                        Interaction $interaction,
                                        object      $command): void
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
                    $arguments["product-id"]["value"]
                );
                $message->setContent(self::printResult($reply));
            } else {
                $message->setContent(self::ACCOUNT_NOT_FOUND);
            }
        } else {
            $message->setContent(self::OBJECT_NOT_FOUND);
        }
        $plan->utilities->acknowledgeCommandMessage(
            $interaction,
            $message,
            true
        );
    }

    public static function remove_product(DiscordPlan $plan,
                                          Interaction $interaction,
                                          object      $command): void
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
                    $arguments["product-id"]["value"]
                );
                $message->setContent(self::printResult($reply));
            } else {
                $message->setContent(self::ACCOUNT_NOT_FOUND);
            }
        } else {
            $message->setContent(self::OBJECT_NOT_FOUND);
        }
        $plan->utilities->acknowledgeCommandMessage(
            $interaction,
            $message,
            true
        );
    }

    public static function exchange_product(DiscordPlan $plan,
                                            Interaction $interaction,
                                            object      $command): void
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
                    $arguments["product-id"]["value"],
                    null,
                    $arguments["exchange-product-id"]["value"]
                );
                $message->setContent(self::printResult($reply));
            } else {
                $message->setContent(self::ACCOUNT_NOT_FOUND);
            }
        } else {
            $message->setContent(self::OBJECT_NOT_FOUND);
        }
        $plan->utilities->acknowledgeCommandMessage(
            $interaction,
            $message,
            true
        );
    }

    public static function delete_account(DiscordPlan $plan,
                                          Interaction $interaction,
                                          object      $command): void
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
                    $arguments["permanent"]["value"]
                );
                $message->setContent(self::printResult($reply));
            } else {
                $message->setContent(self::ACCOUNT_NOT_FOUND);
            }
        } else {
            $message->setContent(self::OBJECT_NOT_FOUND);
        }
        $plan->utilities->acknowledgeCommandMessage(
            $interaction,
            $message,
            true
        );
    }

    public static function queue_paypal_transaction(DiscordPlan $plan,
                                                    Interaction $interaction,
                                                    object      $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $plan->utilities->acknowledgeCommandMessage(
            $interaction,
            MessageBuilder::new()->setContent(
                strval(queue_paypal_transaction($arguments["transaction-id"]["value"]))
            ),
            true
        );
    }

    public static function fail_paypal_transaction(DiscordPlan $plan,
                                                   Interaction $interaction,
                                                   object      $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $plan->utilities->acknowledgeCommandMessage(
            $interaction,
            MessageBuilder::new()->setContent(
                strval(process_failed_paypal_transaction($arguments["transaction-id"]["value"]))
            ),
            true
        );
    }

    public static function suspend_paypal_transactions(DiscordPlan $plan,
                                                       Interaction $interaction,
                                                       object      $command): void
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
                    $reason = $arguments["reason"]["value"];
                    $coverFees = $arguments["cover-fees"]["value"];
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
        $plan->utilities->acknowledgeCommandMessage(
            $interaction,
            $message,
            true
        );
    }

    public static function account_functionality(DiscordPlan $plan,
                                                 Interaction $interaction,
                                                 object      $command): void
    {
        $staffAccount = AccountMessageCreationListener::findAccountFromSession($interaction, $plan);
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
                        $block = $arguments["block"]["value"];
                        $reason = $arguments["reason"]["value"];
                        $duration = $arguments["duration"]["value"];
                        $select = SelectMenu::new();
                        $select->setMinValues(1);
                        $select->setMaxValues(1);
                        $select->setPlaceholder("Select a functionality.");

                        foreach ($functionalities as $id => $functionality) {
                            $option = Option::new(substr($functionality, 0, 100), $id);
                            $select->addOption($option);
                        }

                        $select->setListener(function (Interaction $interaction, Collection $options)
                        use ($block, $reason, $duration, $plan, $account, $staffAccount) {
                            $plan->utilities->acknowledgeMessage(
                                $interaction,
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
                                },
                                true
                            );
                        }, $plan->bot->discord, true);
                        $message->addComponent($select);
                    }
                } else {
                    $message->setContent(self::ACCOUNT_NOT_FOUND);
                }
            } else {
                $message->setContent(self::OBJECT_NOT_FOUND);
            }
            $plan->utilities->acknowledgeCommandMessage(
                $interaction,
                $message,
                true
            );
        }
    }

    public static function account_moderation(DiscordPlan $plan,
                                              Interaction $interaction,
                                              object      $command): void
    {
        $staffAccount = AccountMessageCreationListener::findAccountFromSession($interaction, $plan);
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
                        $punish = $arguments["punish"]["value"];
                        $reason = $arguments["reason"]["value"];
                        $duration = $arguments["duration"]["value"];
                        $select = SelectMenu::new();
                        $select->setMinValues(1);
                        $select->setMaxValues(1);
                        $select->setPlaceholder("Select a functionality.");

                        foreach ($moderations as $id => $moderation) {
                            $option = Option::new(substr($moderation, 0, 100), $id);
                            $select->addOption($option);
                        }

                        $select->setListener(function (Interaction $interaction, Collection $options)
                        use ($punish, $reason, $duration, $plan, $account, $staffAccount) {
                            $plan->utilities->acknowledgeMessage(
                                $interaction,
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
                                },
                                true
                            );
                        }, $plan->bot->discord, true);
                        $message->addComponent($select);
                    }
                } else {
                    $message->setContent(self::ACCOUNT_NOT_FOUND);
                }
            } else {
                $message->setContent(self::OBJECT_NOT_FOUND);
            }
            $plan->utilities->acknowledgeCommandMessage(
                $interaction,
                $message,
                true
            );
        }
    }

    public static function configuration_changes(DiscordPlan $plan,
                                                 Interaction $interaction,
                                                 object      $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $message = new MessageBuilder();
        $gameCloudUser = new GameCloudUser(
            $arguments["platform-id"]["value"],
            $arguments["license-id"]["value"]
        );
        if ($arguments["add"]["value"]) {
            $result = $gameCloudUser->getActions()->addAutomaticConfigurationChange(
                $arguments["version"]["value"],
                $arguments["file-name"]["value"],
                $arguments["option-name"]["value"],
                $arguments["option-value"]["value"],
                $arguments["product-id"]["value"],
                $arguments["email"]["value"]
            );
        } else {
            $result = $gameCloudUser->getActions()->removeAutomaticConfigurationChange(
                $arguments["version"]["value"],
                $arguments["file-name"]["value"],
                $arguments["option-name"]["value"],
                $arguments["product-id"]["value"]
            );
        }
        $plan->utilities->acknowledgeCommandMessage(
            $interaction,
            $message->setContent(strval($result)),
            true
        );
    }

    public static function disabled_detections(DiscordPlan $plan,
                                               Interaction $interaction,
                                               object      $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $message = new MessageBuilder();
        $gameCloudUser = new GameCloudUser(
            $arguments["platform-id"]["value"],
            $arguments["license-id"]["value"]
        );
        if ($arguments["add"]["value"]) {
            $result = $gameCloudUser->getActions()->addDisabledDetection(
                $arguments["plugin-version"]["value"],
                $arguments["server-version"]["value"],
                $arguments["check"]["value"],
                $arguments["detection"]["value"],
                $arguments["email"]["value"]
            );
        } else {
            $result = $gameCloudUser->getActions()->removeDisabledDetection(
                $arguments["plugin-version"]["value"],
                $arguments["server-version"]["value"],
                $arguments["check"]["value"],
                $arguments["detection"]["value"],
            );
        }
        $plan->utilities->acknowledgeCommandMessage(
            $interaction,
            $message->setContent(strval($result)),
            true
        );
    }

    public static function manage_platform_user(DiscordPlan $plan,
                                                Interaction $interaction,
                                                object      $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $gameCloudUser = new GameCloudUser(
            $arguments["platform-id"]["value"],
            $arguments["license-id"]["value"]
        );
        $reason = $arguments["reason"]["value"];
        $duration = $arguments["duration"]["value"];
        $product = $arguments["product-id"]["value"];
        $type = $arguments["type"]["value"];

        if ($arguments["add"]["value"]) {
            $message = strval($gameCloudUser->getVerification()->addLicenseManagement(
                $product,
                $type,
                !empty($reason) ? $reason : null,
                !empty($duration) ? get_future_date($duration) : null
            ));
        } else {
            $message = strval($gameCloudUser->getVerification()->removeLicenseManagement(
                $product,
                $type
            ));
        }
        $plan->utilities->acknowledgeCommandMessage(
            $interaction,
            MessageBuilder::new()->setContent($message),
            true
        );
    }

    public static function financial_input(DiscordPlan $plan,
                                           Interaction $interaction,
                                           object      $command): void
    {
        $arguments = $interaction->data->options->toArray();
        $year = $arguments["year"]["value"];
        $month = $arguments["month"]["value"];
        $results = get_financial_input($year, $month);

        if (!empty($results)) {
            if (array_key_exists("total", $results)) {
                $message = json_encode($results["total"]);
            } else {
                $message = "No information found. (2)";
            }
        } else {
            $message = "No information found. (1)";
        }
        $plan->utilities->acknowledgeCommandMessage(
            $interaction,
            MessageBuilder::new()->setContent($message),
            true
        );
    }

}