<?php

class ChatAI
{
    private int $modelID;
    private string $code, $name, $description;
    private object $parameter, $currency;
    private float $received_token_cost, $sent_token_cost;
    private bool $exists;

    public function __construct(?int $model)
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

    public function getResult($hash, $apiKey, array $parameters)
    {

    }
}