<?php

class BotDatabaseTable
{
    public const
        BOT_COUNTING = "discord.botCounting",
        BOT_COUNTING_MESSAGES = "discord.botCountingMessages",
        BOT_COUNTING_GOALS = "discord.botCountingGoals",
        BOT_COUNTING_GOAL_STORAGE = "discord.botCountingGoalStorage",
        BOT_MESSAGE_REMINDERS = "discord.botMessageReminders",
        BOT_MESSAGE_REMINDER_TRACKING = "discord.botMessageReminderTracking",
        BOT_NOTES = "discord.botNotes",
        BOT_NOTE_CHANGES = "discord.botNoteChanges",
        BOT_NOTE_SETTINGS = "discord.botNoteSettings",
        BOT_NOTE_PARTICIPANTS = "discord.botNoteParticipants",
        BOT_STATISTICS_CHANNELS = "discord.botStatisticsChannels",
        BOT_INVITE_TRACKER = "discord.botInviteTracker",
        BOT_INVITE_TRACKER_GOALS = "discord.botInviteTrackerGoals",
        BOT_INVITE_TRACKER_GOAL_STORAGE = "discord.botInviteTrackerGoalStorage",
        BOT_LEVELS = "discord.botLevels",
        BOT_LEVEL_CHANNELS = "discord.botLevelChannels",
        BOT_LEVEL_TIERS = "discord.botLevelTiers",
        BOT_LEVEL_TRACKING = "discord.botLevelTracking",
        BOT_PLANS = "discord.botPlans",
        BOT_CHANNELS = "discord.botChannels",
        BOT_CHANNEL_LOGS = "discord.botChannelLogs",
        BOT_CHANNEL_WHITELIST = "discord.botChannelWhitelist",
        BOT_CHANNEL_BLACKLIST = "discord.botChannelBlacklist",
        BOT_LOGS = "discord.botLogs",
        BOT_TARGETED_MESSAGES = "discord.botTargetedMessages",
        BOT_TARGETED_MESSAGE_INSTRUCTIONS = "discord.botTargetedMessageInstructions",
        BOT_TARGETED_MESSAGE_ROLES = "discord.botTargetedMessageRoles",
        BOT_TARGETED_MESSAGE_CREATIONS = "discord.botTargetedMessageCreations",
        BOT_TEMPORARY_CHANNELS = "discord.botTemporaryChannels",
        BOT_TEMPORARY_CHANNEL_TRACKING = "discord.botTemporaryChannelTracking",
        BOT_TEMPORARY_CHANNEL_OWNERS = "discord.botTemporaryChannelOwners",
        BOT_TEMPORARY_CHANNEL_BANS = "discord.botTemporaryChannelBans",
        BOT_TEMPORARY_CHANNEL_ROLES = "discord.botTemporaryChannelRoles",
        BOT_QUESTIONNAIRES = "discord.botQuestionnaires",
        BOT_QUESTIONNAIRE_TRACKING = "discord.botQuestionnaireTracking",
        BOT_QUESTIONNAIRE_QUESTIONS = "discord.botQuestionnaireQuestions",
        BOT_QUESTIONNAIRE_ANSWERS = "discord.botQuestionnaireAnswers",
        BOT_QUESTIONNAIRE_ROLES = "discord.botQuestionnaireRoles",
        BOT_QUESTIONNAIRE_INSTRUCTIONS = "discord.botQuestionnaireInstructions",
        BOT_INTERACTION_ROLES = "discord.botInteractionRoles",
        BOT_INTERACTION_ROLE_TRACKING = "discord.botInteractionRoleTracking",
        BOT_INTERACTION_ROLE_CHOICES = "discord.botInteractionRoleChoices",
        BOT_POLLS = "discord.botPolls",
        BOT_POLL_TRACKING = "discord.botPollTracking",
        BOT_POLL_CHOICES = "discord.botPollChoices",
        BOT_POLL_CHOICE_TRACKING = "discord.botPollChoiceTracking",
        BOT_POLL_PERMISSIONS = "discord.botPollPermissions",
        BOT_POLL_ROLES = "discord.botPollRoles",
        BOT_MUTE = "discord.botMute",
        BOT_OBJECTIVE_CHANNELS = "discord.botObjectiveChannels",
        BOT_OBJECTIVE_CHANNEL_TRACKING = "discord.botObjectiveChannelTracking",
        BOT_OBJECTIVE_CHANNEL_ROLES = "discord.botObjectiveChannelRoles",
        BOT_MESSAGE_TRANSFERRER = "discord.botMessageTransferrer",
        BOT_MESSAGE_TRANSFERRER_CHANNELS = "discord.botMessageTransferrerChannels",
        BOT_MESSAGE_TRANSFERRER_TRACKING = "discord.botMessageTransferrerTracking",
        BOT_MESSAGE_MESSAGE_FILTER = "discord.botMessageFilter",
        BOT_MESSAGE_MESSAGE_FILTER_BLOCKED_WORDS = "discord.botMessageFilterBlockedWords",
        BOT_MESSAGE_MESSAGE_FILTER_LETTER_CORRELATIONS = "discord.botMessageFilterLetterCorrelations",
        BOT_MESSAGE_MESSAGE_FILTER_INSTRUCTIONS = "discord.botMessageFilterInstructions",
        BOT_MESSAGE_MESSAGE_FILTER_CONSTANTS = "discord.botMessageFilterConstants",
        BOT_MESSAGE_MESSAGE_FILTER_KEYWORDS = "discord.botMessageFilterKeywords",
        BOT_MESSAGE_MESSAGE_FILTER_TRACKING = "discord.botMessageFilterTracking",
        BOT_GIVEAWAYS = "discord.botGiveaways",
        BOT_GIVEAWAY_TRACKING = "discord.botGiveawayTracking",
        BOT_GIVEAWAY_PARTICIPANTS = "discord.botGiveawayParticipants",
        BOT_GIVEAWAYS_WINNERS = "discord.botGiveawayWinners",
        BOT_GIVEAWAYS_PERMISSIONS = "discord.botGiveawayPermissions",
        BOT_GIVEAWAYS_ROLES = "discord.botGiveawayRoles",
        BOT_MESSAGE_NOTIFICATIONS = "discord.botMessageNotifications",
        BOT_MESSAGE_NOTIFICATION_TRACKING = "discord.botMessageNotificationTracking",
        BOT_MESSAGE_NOTIFICATION_ROLES = "discord.botMessageNotificationRoles",
        BOT_MESSAGE_NOTIFICATION_INSTRUCTIONS = "discord.botMessageNotificationInstructions",
        BOT_ANTI_EXPIRATION_THREADS = "discord.botAntiExpirationThreads",
        BOT_TICKETS = "discord.botTickets",
        BOT_TICKET_ROLES = "discord.botTicketRoles",
        BOT_TICKET_CREATIONS = "discord.botTicketCreations",
        BOT_TICKET_SUB_CREATIONS = "discord.botTicketSubCreations",
        BOT_TICKET_MESSAGES = "discord.botTicketMessages",
        BOT_MODAL_COMPONENTS = "discord.botModalComponents",
        BOT_MODAL_SUB_COMPONENTS = "discord.botModalSubComponents",
        BOT_REACTION_COMPONENTS = "discord.botReactionComponents",
        BOT_BUTTON_COMPONENTS = "discord.botButtonComponents",
        BOT_SELECTION_COMPONENTS = "discord.botSelectionComponents",
        BOT_SELECTION_SUB_COMPONENTS = "discord.botSelectionSubComponents",
        BOT_CONTROLLED_MESSAGES = "discord.botControlledMessages",
        BOT_ERRORS = "discord.botErrors",
        BOT_AI_COST_LIMITS = "discord.botAICostLimits",
        BOT_AI_COST_TIMEOUTS = "discord.botAICostTimeouts",
        BOT_AI_MESSAGE_LIMITS = "discord.botAIMessageLimits",
        BOT_AI_MESSAGE_TIMEOUTS = "discord.botAIMessageTimeouts",
        BOT_AI_KEYWORDS = "discord.botAIKeywords",
        BOT_AI_CHAT_MODEL = "discord.botAIChatModel",
        BOT_AI_INSTRUCTIONS = "discord.botAIInstructions",
        BOT_AI_FEEDBACK = "discord.botAIFeedback",
        BOT_AI_MENTIONS = "discord.botAIMentions",
        BOT_AI_MESSAGES = "discord.botAIMessages",
        BOT_AI_REPLIES = "discord.botAIReplies",
        BOT_ROLE_PERMISSIONS = "discord.botRolePermissions",
        BOT_USER_PERMISSIONS = "discord.botUserPermissions",
        BOT_COMMANDS = "discord.botCommands",
        BOT_COMMAND_ARGUMENTS = "discord.botCommandArguments",
        BOT_JOIN_ROLES = "discord.botJoinRoles",
        BOT_JOIN_ROLE_TRACKING = "discord.botJoinRoleTracking",
        BOT_FAQ = "discord.botFAQ";
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
        LIGHT_CODE_BLOCK = "``",
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
        $string = str_replace("</div>", "\n",
            str_replace("<u>", DiscordSyntax::UNDERLINE,
                str_replace("</u>", DiscordSyntax::UNDERLINE,
                    str_replace("<i>", DiscordSyntax::ITALICS,
                        str_replace("</i>", DiscordSyntax::ITALICS,
                            str_replace("<b>", DiscordSyntax::BOLD,
                                str_replace("</b>", DiscordSyntax::BOLD,
                                    $string
                                )
                            )
                        )
                    )
                )
            )
        );
        $string = str_replace("<br>", "\n",
            str_replace("<li>", "\n",
                str_replace("<p>", "\n",
                    str_replace("</ul>", "\n",
                        $string
                    )
                )
            )
        );
        return strip_tags(self::htmlLinkToDiscordLink($string));
    }

    public static function htmlLinkToDiscordLink(string $string): string
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

class DiscordPredictedLimits
{
    public const RAPID_CHANNEL_MODIFICATIONS = 20;
}

class DiscordInheritedLimits
{
    public const
        MAX_EMBEDS_PER_MESSAGE = 10,
        MAX_ARGUMENTS_PER_COMMAND = 25,
        MAX_FIELDS_PER_EMBED = 25,
        MAX_CHOICES_PER_SELECTION = 25,
        MAX_BUTTONS_PER_ACTION_ROW = 5,
        MAX_ACTION_ROWS_PER_MESSAGE = 5,
        MESSAGE_MAX_LENGTH = 2000,
        MESSAGE_NITRO_MAX_LENGTH = 4000,
        EMBED_COLLECTIVE_LENGTH_LIMIT = 6000,
        MAX_FIELD_KEY_LENGTH = 256,
        MAX_FIELD_VALUE_LENGTH = 1024;
}

class DiscordProperties
{
    public const
        DEFAULT_PROMPT_MESSAGE = "...",
        SYSTEM_REFRESH_MILLISECONDS = 60_000, // 1 minute
        SYSTEM_REFRESH_TIME = (DiscordProperties::SYSTEM_REFRESH_MILLISECONDS / 1_000) . " seconds",
        NEW_LINE = "\n";
}
