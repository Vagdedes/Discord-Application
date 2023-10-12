<?php

class ChatAI
{
    public int $modelID;
    private string $apiKey;
    public string $code, $name, $description;
    public object $parameter, $currency;
    public float $received_token_cost, $sent_token_cost;
    public bool $exists;

    public function __construct(?int $model, string $apiKey)
    {
        $query = get_sql_query(
            AIDatabaseTable::AI_MODELS,
            null,
            array(
                array("id", $model),
                array("deletion_date", null),
            ),
            null,
            1
        );

        if (!empty($query)) {
            $query = $query[0];
            $queryChild = get_sql_query(
                AIDatabaseTable::AI_PARAMETERS,
                null,
                array(
                    array("id", $query->parameter_id),
                    array("deletion_date", null),
                ),
                null,
                1
            );

            if (!empty($queryChild)) {
                $this->parameter = $queryChild[0];
                $queryChild = get_sql_query(
                    AIDatabaseTable::AI_CURRENCIES,
                    null,
                    array(
                        array("id", $query->currency_id),
                        array("deletion_date", null),
                    ),
                    null,
                    1
                );

                if (!empty($queryChild)) {
                    $this->apiKey = $apiKey;
                    $this->currency = $queryChild[0];
                    $this->modelID = $query->id;
                    $this->code = $query->code;
                    $this->name = $query->name;
                    $this->description = $query->description;
                    $this->received_token_cost = $query->received_token_cost;
                    $this->sent_token_cost = $query->sent_token_cost;
                    $this->exists = true;
                } else {
                    $this->exists = false;
                }
            } else {
                $this->exists = false;
            }
        } else {
            $this->exists = false;
        }
    }

    public function getHistory($hash, ?bool $failure = null, ?int $limit = 0): array
    {
        set_sql_cache("1 second");
        return get_sql_query(
            AIDatabaseTable::AI_TEXT_HISTORY,
            null,
            array(
                array("hash", $hash),
                $failure !== null ? array("failure", $failure) : "",
            ),
            array(
                "DESC",
                "id"
            ),
            $limit
        );
    }

    public function getResult($hash, array $parameters, ?int $timeout = 30)
    {
        switch ($this->modelID) {
            case AIModel::CHAT_GPT_3_5:
                $link = "https://api.openai.com/v1/chat/completions";
                break;
            default:
                $link = null;
                break;
        }

        if ($link !== null) {
            switch ($this->parameter->id) {
                case AIParameterType::JSON:
                    $contentType = "application/json";
                    break;
                default:
                    $contentType = null;
                    break;
            }

            if ($contentType !== null) {
                $parameters["model"] = $this->code;
                $parameters = json_encode($parameters);
                $reply = get_curl(
                    $link,
                    "POST",
                    array(
                        "Content-Type: " . $contentType,
                        "Authorization: Bearer " . $this->apiKey
                    ),
                    $parameters,
                    $timeout
                );

                if ($reply !== null
                    && $reply !== false) {
                    $received = $reply;
                    $reply = json_decode($reply);

                    if (is_object($reply)) {
                        if (isset($reply->usage->prompt_tokens)
                            && isset($reply->usage->completion_tokens)) {
                            sql_insert(
                                AIDatabaseTable::AI_TEXT_HISTORY,
                                array(
                                    "model_id" => $this->modelID,
                                    "hash" => $hash,
                                    "sent_parameters" => $parameters,
                                    "received_parameters" => $received,
                                    "sent_tokens" => $reply->usage->prompt_tokens,
                                    "received_tokens" => $reply->usage->completion_tokens,
                                    "currency_id" => $this->currency->id,
                                    "sent_token_cost" => ($reply->usage->prompt_tokens * $this->sent_token_cost),
                                    "received_token_cost" => ($reply->usage->completion_tokens * $this->received_token_cost),
                                    "creation_date" => get_current_date()
                                )
                            );
                            return $reply;
                        } else {
                            $failure = true;
                        }
                    } else {
                        $failure = true;
                    }
                } else {
                    $failure = true;
                }

                if ($failure) {
                    sql_insert(
                        AIDatabaseTable::AI_TEXT_HISTORY,
                        array(
                            "model_id" => $this->modelID,
                            "hash" => $hash,
                            "failure" => true,
                            "sent_parameters" => $parameters,
                            "currency_id" => $this->currency->id,
                            "creation_date" => get_current_date()
                        )
                    );
                }
            }
        }
        return null;
    }

    public function getText($object): ?string
    {
        switch ($this->modelID) {
            case AIModel::CHAT_GPT_3_5:
                return $object?->choices[0]?->message->content;
            default:
                return null;
        }
    }
}