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
        BOT_WHITELIST = "discord.botWhitelist",
        BOT_LIMITS = "discord.botLimits",
        BOT_LIMIT_TRACKING = "discord.botLimitTracking",
        CURRENCIES = "discord.currencies",
        BOT_COMMANDS = "discord.botCommands";
}

class DiscordPunishment
{
    public const
        DISCORD_BAN = 1,
        DISCORD_KICK = 2,
        DISCORD_TIMEOUT = 3,
        CUSTOM_BLACKLIST = 4;
}

class DiscordSyntax
{
    public const
        ITALICS = "*",
        UNDERLINE_ITALICS = array("__*", "*__"),
        BOLD = "**",
        UNDERLINE_BOLD = array("__**", "**__"),
        BOLD_ITALICS = "***",
        UNDERLINE_BOLD_ITALICS = array("__***", "***__"),
        UNDERLINE = "__",
        STRIKETHROUGH = "~~",
        BIG_HEADER = "#",
        MEDIUM_HEADER = "##",
        SMALL_HEADER = "###",
        LIST = "-",
        CODE_BLOCK = "`",
        HEAVY_CODE_BLOCK = "```",
        QUOTE = ">",
        MULTI_QUOTE = ">>>",
        SPOILER = "||";
}

class DiscordProperties
{
    private const STRICT_REPLY_INSTRUCTIONS_DEFAULT = "IMPORTANT: "
    . "IF THE USER MESSAGE IS UNRELATED TO THE INFORMATION PROVIDED";

    public const
        MESSAGE_MAX_LENGTH = 2000,
        MESSAGE_NITRO_MAX_LENGTH = 4000,
        SYSTEM_REFRESH_MILLISECONDS = 300_000, // 5 minutes
        NEW_LINE = "\n",
        DEFAULT_PLACEHOLDER_START = "%%__",
        DEFAULT_PLACEHOLDER_MIDDLE = "__",
        DEFAULT_PLACEHOLDER_END = "__%%",
        NO_REPLY = self::DEFAULT_PLACEHOLDER_START . "noReply" . self::DEFAULT_PLACEHOLDER_END,
        STRICT_REPLY_INSTRUCTIONS = self::STRICT_REPLY_INSTRUCTIONS_DEFAULT . ", REPLY WITH JUST: " . self::NO_REPLY,
        STRICT_REPLY_INSTRUCTIONS_WITH_MENTION = self::STRICT_REPLY_INSTRUCTIONS_DEFAULT . ", KINDLY NOTIFY THE USER.";
}
