<?php

class DiscordInstructions
{

    private DiscordPlan $plan;
    private array $instructions;
    private array $placeholders;

    public function __construct(DiscordPlan $plan)
    {
        $this->plan = $plan;
        $this->refresh();
    }

    public function refresh(): void
    {
        $this->instructions = get_sql_query(
            BotDatabaseTable::BOT_INSTRUCTIONS,
            null,
            array(
                array("plan_id", $this->plan->planID),
                array("deletion_date", null),
                null,
                array("expiration_date", "IS", null, 0),
                array("expiration_date", ">", get_current_date()),
                null
            )
        );
        $this->placeholders = get_sql_query(
            BotDatabaseTable::BOT_INSTRUCTION_PLACEHOLDERS,
            null,
            array(
                array("plan_id", $this->plan->planID),
                array("deletion_date", null),
                null,
                array("expiration_date", "IS", null, 0),
                array("expiration_date", ">", get_current_date()),
                null
            )
        );
    }

    public function build(): string
    {
        //todo
        return "";
    }
}