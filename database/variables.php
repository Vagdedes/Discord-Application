<?php
$sql_credentials = get_keys_from_file("/root/discord_bot/private/credentials/sql_credentials", 3);

if ($sql_credentials === null) {
    exit("Database credentials not found");
}
sql_sql_credentials($sql_credentials[0],
    $sql_credentials[1],
    $sql_credentials[2],
    null,
    null,
    null,
    true);

class DatabaseVariables
{

    public const
        BOT_PLANS_TABLE = "discord.botPlans",
        BOT_CHANNELS_TABLE = "discord.botChannels",
        BOT_LOGS_TABLE = "discord.botLogs",
        BOT_INSTRUCTIONS_TABLE = "discord.botInstructions",
        BOT_INSTRUCTION_PLACEHOLDERS_TABLE = "discord.botInstructionPlaceholders",
        BOT_MESSAGES_TABLE = "discord.botMessages",
        BOT_REPLIES_TABLE = "discord.botReplies",
        BOT_STATIC_KNOWLEDGE_TABLE = "discord.botStaticKnowledge",
        BOT_DYNAMIC_KNOWLEDGE_TABLE = "discord.botDynamicKnowledge",
        BOT_PUNISHMENTS_TABLE = "discord.botPunishments",
        BOT_PUNISHMENT_TYPES_TABLE = "discord.botPunishmentTypes",
        BOT_WHITELIST_TABLE = "discord.botWhitelist";
}
