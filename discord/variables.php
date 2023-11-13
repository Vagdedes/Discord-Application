<?php

class BotDatabaseTable
{
    public const
        BOT_PLANS = "discord.botPlans",
        BOT_CHANNELS = "discord.botChannels",
        BOT_LOGS = "discord.botLogs",
        BOT_GOALS = "discord.botGoals",
        BOT_GOAL_EXECUTIONS = "discord.botGoalExecutions",
        BOT_GOAL_MESSAGES = "discord.botGoalMessages",
        BOT_MESSAGE_REFRESH = "discord.botMessageRefresh",
        BOT_TICKETS = "discord.botTickets",
        BOT_TICKET_ROLES = "discord.botTicketRoles",
        BOT_TICKET_CREATIONS = "discord.botTicketCreations",
        BOT_TICKET_SUB_CREATIONS = "discord.botTicketSubCreations",
        BOT_TICKET_MESSAGES = "discord.botTicketMessages",
        BOT_MODAL_COMPONENTS = "discord.botModalComponents",
        BOT_MODAL_SUB_COMPONENTS = "discord.botModalSubComponents",
        BOT_BUTTON_COMPONENTS = "discord.botButtonComponents",
        BOT_SELECTION_COMPONENTS = "discord.botSelectionComponents",
        BOT_SELECTION_SUB_COMPONENTS = "discord.botSelectionSubComponents",
        BOT_CONTROLLED_MESSAGES = "discord.botControlledMessages",
        BOT_ERRORS = "discord.botErrors",
        BOT_MENTIONS = "discord.botMentions",
        BOT_LOCAL_INSTRUCTIONS = "discord.botLocalInstructions",
        BOT_PUBLIC_INSTRUCTIONS = "discord.botPublicInstructions",
        BOT_INSTRUCTION_PLACEHOLDERS = "discord.botInstructionPlaceholders",
        BOT_MESSAGES = "discord.botMessages",
        BOT_REPLIES = "discord.botReplies",
        BOT_PUNISHMENTS = "discord.botPunishments",
        BOT_PUNISHMENT_TYPES = "discord.botPunishmentTypes",
        BOT_WHITELIST = "discord.botWhitelist",
        BOT_COST_LIMITS = "discord.botMessageLimits",
        BOT_MESSAGE_LIMITS = "discord.botMessageLimits",
        BOT_KEYWORDS = "discord.botKeywords",
        BOT_CHAT_MODEL = "discord.botChatModel",
        CURRENCIES = "discord.currencies",
        BOT_ROLE_PERMISSIONS = "discord.botRolePermissions",
        BOT_COMMANDS = "discord.botCommands",
        BOT_COMMAND_ARGUMENTS = "discord.botCommandArguments";
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

    public static function htmlToDiscord(string $string): string
    {
        $string = str_replace("<h1>", DiscordSyntax::BIG_HEADER,
            str_replace("</h1>", "\n",
                str_replace("<h2>", DiscordSyntax::MEDIUM_HEADER,
                    str_replace("</h2>", "\n",
                        str_replace("<h3>", DiscordSyntax::SMALL_HEADER,
                            str_replace("</h3>", "\n",
                                $string
                            )
                        )
                    )
                )
            )
        );
        $string = str_replace("<br>", "\n",
            str_replace("</div>", "\n",
                str_replace("<u>", DiscordSyntax::UNDERLINE,
                    str_replace("</u>", DiscordSyntax::UNDERLINE,
                        str_replace("<i>", DiscordSyntax::ITALICS,
                            str_replace("</i>", DiscordSyntax::ITALICS,
                                str_replace("<b>", DiscordSyntax::BOLD,
                                    str_replace("</b>", DiscordSyntax::BOLD, $string)
                                )
                            )
                        )
                    )
                )
            )
        );
        $string = str_replace("<li>", "\n",
            str_replace("<p>", "\n",
                str_replace("</ul>", "\n",
                    $string
                )
            )
        );
        return strip_tags(self::htmlLinkToDiscordLink($string));
    }

    public static function htmlLinkToDiscordLink($string): string
    {
        $original = $string;
        $string = explode("<a href='", $string, 2);

        if (sizeof($string) === 1) {
            return $string[0];
        } else {
            $string = explode("'>", $string[1], 2);
            $url = $string[0];
            $text = explode("</a>", $string[1], 2)[0];
            $final = str_replace(
                "<a href='" . $url . "'>" . $text . "</a>",
                "[" . $text . "](" . $url . ")",
                $original
            );
            return self::htmlLinkToDiscordLink($final);
        }
    }
}

class DiscordProperties
{
    private const STRICT_REPLY_INSTRUCTIONS_DEFAULT = "MOST IMPORTANT: "
    . "IF YOU ARE NOT OVER 90% CERTAIN THE USER'S MESSAGE IS RELATED TO THE FOLLOWING INFORMATION";

    public const
        MAX_EMBED_PER_MESSAGE = 25,
        MAX_BUTTONS_PER_ACTION_ROW = 5,
        MESSAGE_MAX_LENGTH = 2000,
        MESSAGE_NITRO_MAX_LENGTH = 4000,
        SYSTEM_REFRESH_MILLISECONDS = 900_000, // 15 minutes
        NEW_LINE = "\n",
        DEFAULT_PLACEHOLDER_START = "%%__",
        DEFAULT_PLACEHOLDER_MIDDLE = "__",
        DEFAULT_PLACEHOLDER_END = "__%%",
        NO_REPLY = self::DEFAULT_PLACEHOLDER_START . "empty" . self::DEFAULT_PLACEHOLDER_END,
        STRICT_REPLY_INSTRUCTIONS = self::STRICT_REPLY_INSTRUCTIONS_DEFAULT . ", JUST REPLY WITH: " . self::NO_REPLY,
        STRICT_REPLY_INSTRUCTIONS_WITH_MENTION = self::STRICT_REPLY_INSTRUCTIONS_DEFAULT . ", KINDLY NOTIFY THE USER.";
}
