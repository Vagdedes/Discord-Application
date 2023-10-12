<?php
$sql_credentials = get_keys_from_file("/root/discord_bot/private/credentials/sql_credentials", 3);

if ($sql_credentials === null) {
    exit("Database credentials not found");
}
sql_sql_credentials(
    $sql_credentials[0],
    $sql_credentials[1],
    $sql_credentials[2],
    null,
    null,
    null,
    true
);

class BotDatabaseTable
{
    public const
        BOT_PLANS = "discord.botPlans",
        BOT_CHANNELS = "discord.botChannels",
        BOT_LOGS = "discord.botLogs",
        BOT_INSTRUCTIONS = "discord.botInstructions",
        BOT_INSTRUCTION_PLACEHOLDERS = "discord.botInstructionPlaceholders",
        BOT_MESSAGES = "discord.botMessages",
        BOT_REPLIES = "discord.botReplies",
        BOT_STATIC_KNOWLEDGE = "discord.botStaticKnowledge",
        BOT_DYNAMIC_KNOWLEDGE = "discord.botDynamicKnowledge",
        BOT_PUNISHMENTS = "discord.botPunishments",
        BOT_PUNISHMENT_TYPES = "discord.botPunishmentTypes",
        BOT_WHITELIST = "discord.botWhitelist";
}