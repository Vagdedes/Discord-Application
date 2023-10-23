<?php

class ChatModel
{
    public int $modelID, $context;
    public string $code, $name, $description;
    public object $parameter, $currency;
    public float $received_token_cost, $sent_token_cost;
    public bool $exists;

    public function __construct(int|string $modelID)
    {
        $query = get_sql_query(
            AIDatabaseTable::AI_MODELS,
            null,
            array(
                array("id", $modelID),
            ),
            null,
            1
        );

        if (!empty($query)) {
            $query = $query[0];
            set_sql_cache("1 second"); // Case in case it uses the same parameter
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

                set_sql_cache("1 second"); // Case in case it uses the same currency
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
                    $this->exists = true;
                    $this->currency = $queryChild[0];
                    $this->modelID = $query->id;
                    $this->context = $query->context;
                    $this->code = $query->code;
                    $this->name = $query->name;
                    $this->description = $query->description;
                    $this->received_token_cost = $query->received_token_cost;
                    $this->sent_token_cost = $query->sent_token_cost;
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
}