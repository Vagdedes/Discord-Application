<?php

class DiscordCurrency
{
    public int $id;
    public string $code, $creationDate;
    public bool $exists;
    public ?string $creationReason;

    public function __construct($code)
    {
        $this->code = $code;
        set_sql_cache(DiscordProperties::SYSTEM_REFRESH_MILLISECONDS);
        $query = get_sql_query(
            BotDatabaseTable::CURRENCIES,
            null,
            array(
                array("code", $code),
                array("deletion_date", null),
            ),
            null,
            1
        );

        if (!empty($query)) {
            $query = $query[0];
            $this->id = $query->id;
            $this->creationDate = $query->creation_date;
            $this->creationReason = $query->creation_reason;
            $this->exists = true;
        } else {
            $this->exists = false;
        }
    }
}