<?php
require '/root/discord_bot/utilities/utilities.php';
$token = get_keys_from_file("/root/discord_bot/private/credentials/discord_token", 1);

if ($token === null) {
    exit("No Discord token found");
}
$AI_key = get_keys_from_file("/root/discord_bot/private/credentials/openai_api_key", 1);

if ($AI_key === null) {
    exit("No AI API key found");
}
ini_set('memory_limit', '-1');
require '/root/vendor/autoload.php';

require '/root/discord_bot/utilities/memory/init.php';
require '/root/discord_bot/utilities/sql.php';
require '/root/discord_bot/utilities/runnable.php';

require '/root/discord_bot/discord/variables.php';
require '/root/discord_bot/discord/DiscordPlan.php';
require '/root/discord_bot/discord/DiscordInstructions.php';
require '/root/discord_bot/discord/DiscordLogs.php';
require '/root/discord_bot/discord/DiscordModeration.php';
require '/root/discord_bot/discord/DiscordConversation.php';
require '/root/discord_bot/discord/DiscordLimits.php';
require '/root/discord_bot/discord/DiscordCommands.php';
require '/root/discord_bot/discord/DiscordCurrency.php';
require '/root/discord_bot/discord/DiscordBot.php';

require '/root/discord_bot/ai/variables.php';
require '/root/discord_bot/ai/ChatModel.php';
require '/root/discord_bot/ai/ChatAI.php';

use Discord\Discord;
use Discord\Helpers\Collection;
use Discord\Parts\Channel\Channel;
use Discord\Parts\Channel\Invite;
use Discord\Parts\Channel\Message;
use Discord\Parts\Channel\StageInstance;
use Discord\Parts\Guild\AutoModeration\Rule;
use Discord\Parts\Guild\Ban;
use Discord\Parts\Guild\CommandPermissions;
use Discord\Parts\Guild\Guild;
use Discord\Parts\Guild\Integration;
use Discord\Parts\Guild\Role;
use Discord\Parts\Guild\ScheduledEvent;
use Discord\Parts\Interactions\Interaction;
use Discord\Parts\User\Member;
use Discord\Parts\User\User;
use Discord\Parts\WebSockets\AutoModerationActionExecution;
use Discord\Parts\WebSockets\MessageReaction;
use Discord\Parts\WebSockets\TypingStart;
use Discord\Parts\WebSockets\VoiceServerUpdate;
use Discord\Parts\WebSockets\VoiceStateUpdate;
use Discord\WebSockets\Event;
use Discord\WebSockets\Intents;

$discord = new Discord([
    'token' => $token[0],
    'intents' => Intents::getDefaultIntents() | Intents::GUILD_MEMBERS | Intents::GUILD_PRESENCES | Intents::MESSAGE_CONTENT,
    'storeMessages' => false,
    'retrieveBans' => false,
    'loadAllMembers' => false,
    'disabledEvents' => [],
    'dnsConfig' => '1.1.1.1'
]);
$logger = new DiscordLogs(null);

$discord->on('ready', function (Discord $discord) {
    global $logger;
    $botID = $discord->id;
    $logger = new DiscordLogs($botID);
    $discordBot = new DiscordBot($botID);

    $discord->on(Event::MESSAGE_CREATE, function (Message $message, Discord $discord) use ($discordBot, $botID, $logger) {
        if ($message->guild_id !== null) {
            foreach ($discordBot->getPlans() as $plan) {
                if ($plan->canAssist(
                    $message->guild_id,
                    $message->channel_id,
                    $message->user_id,
                    $message->content,
                    $botID
                )) {
                    $assistance = $plan->assist(
                        $discord,
                        $message,
                        $message->mentions,
                        $message->guild_id,
                        $message->guild->name,
                        $message->channel_id,
                        $message->channel->name,
                        $message->thread?->id,
                        $message->thread?->name,
                        $message->user_id,
                        $message->author->displayname,
                        $message->id,
                        $message->content,
                        $botID,
                        $discord->user->displayname,
                    );

                    if (!empty($assistance)
                        && $assistance !== DiscordProperties::NO_REPLY) {
                        foreach (str_split($assistance, DiscordProperties::MESSAGE_MAX_LENGTH) as $split) {
                            $message->reply($split);
                        }
                    }
                    break;
                }
            }
        }
        $logger->logInfo($message->user_id, Event::MESSAGE_CREATE, $message->getRawAttributes());
    });

    $discord->on(Event::MESSAGE_DELETE, function (object $message, Discord $discord) use ($logger) {
        $logger->logInfo(null, Event::MESSAGE_DELETE, $message);
    });

    $discord->on(Event::MESSAGE_UPDATE, function (Message $message, Discord $discord) use ($logger) {
        $logger->logInfo($message->user_id, Event::MESSAGE_UPDATE, $message->getRawAttributes());
    });

    $discord->on(Event::MESSAGE_DELETE_BULK, function (Collection $messages, Discord $discord) use ($logger) {
        foreach ($messages as $message) {
            $logger->logInfo(null, Event::MESSAGE_DELETE_BULK, $message);
        }
    });

    // Separator

    $discord->on(Event::APPLICATION_COMMAND_PERMISSIONS_UPDATE, function (CommandPermissions $commandPermission, Discord $discord, ?CommandPermissions $oldCommandPermission) use ($logger) {
        $logger->logInfo(null, Event::APPLICATION_COMMAND_PERMISSIONS_UPDATE, $commandPermission->getRawAttributes(), $oldCommandPermission?->getRawAttributes());
    });

    // Separator

    $discord->on(Event::AUTO_MODERATION_RULE_CREATE, function (Rule $rule, Discord $discord) use ($logger) {
        $logger->logInfo($rule->creator->id, Event::AUTO_MODERATION_RULE_CREATE, $rule->getRawAttributes());
    });

    $discord->on(Event::AUTO_MODERATION_RULE_UPDATE, function (Rule $rule, Discord $discord, ?Rule $oldRule) use ($logger) {
        $logger->logInfo(null, Event::AUTO_MODERATION_RULE_UPDATE, $rule->getRawAttributes(), $oldRule?->getRawAttributes());
    });

    $discord->on(Event::AUTO_MODERATION_RULE_DELETE, function (Rule $rule, Discord $discord) use ($logger) {
        $logger->logInfo(null, Event::AUTO_MODERATION_RULE_DELETE, $rule->getRawAttributes());
    });

    $discord->on(Event::AUTO_MODERATION_ACTION_EXECUTION, function (AutoModerationActionExecution $actionExecution, Discord $discord) use ($logger) {
        $logger->logInfo($actionExecution->user_id, Event::AUTO_MODERATION_ACTION_EXECUTION, $actionExecution->getRawAttributes());
    });

    // Separator

    $discord->on(Event::CHANNEL_CREATE, function (Channel $channel, Discord $discord) use ($logger) {
        $logger->logInfo($channel->parent_id, Event::CHANNEL_CREATE, $channel->getRawAttributes());
    });

    $discord->on(Event::CHANNEL_UPDATE, function (Channel $channel, Discord $discord, ?Channel $oldChannel) use ($logger) {
        $logger->logInfo(null, Event::CHANNEL_UPDATE, $channel->getRawAttributes(), $oldChannel?->getRawAttributes());
    });

    $discord->on(Event::CHANNEL_DELETE, function (Channel $channel, Discord $discord) use ($logger) {
        $logger->logInfo(null, Event::CHANNEL_DELETE, $channel->getRawAttributes());
    });

    $discord->on(Event::CHANNEL_PINS_UPDATE, function ($pins, Discord $discord) use ($logger) {
        $logger->logInfo(null, Event::CHANNEL_PINS_UPDATE, $pins);
    });

    // Separator

    $discord->on(Event::THREAD_CREATE, function (Thread $thread, Discord $discord) use ($logger) {
        $logger->logInfo(null, Event::THREAD_CREATE, $thread);
    });

    $discord->on(Event::THREAD_UPDATE, function (Thread $thread, Discord $discord, ?Thread $oldThread) use ($logger) {
        $logger->logInfo(null, Event::THREAD_UPDATE, $thread, $oldThread);
    });

    $discord->on(Event::THREAD_DELETE, function (object $thread, Discord $discord) use ($logger) {
        $logger->logInfo(null, Event::THREAD_DELETE, $thread);
    });

    $discord->on(Event::THREAD_LIST_SYNC, function (Collection $threads, Discord $discord) use ($logger) {
        $logger->logInfo(null, Event::THREAD_LIST_SYNC, $threads);
    });

    $discord->on(Event::THREAD_MEMBER_UPDATE, function (Member $threadMember, Discord $discord) use ($logger) {
        $logger->logInfo(null, Event::THREAD_MEMBER_UPDATE, $threadMember->getRawAttributes());
    });

    $discord->on(Event::THREAD_MEMBERS_UPDATE, function (Thread $thread, Discord $discord) use ($logger) {
        $logger->logInfo(null, Event::THREAD_MEMBERS_UPDATE, $thread);
    });

    // Separator

    $discord->on(Event::GUILD_CREATE, function (object $guild, Discord $discord) use ($logger) {
        if ($guild instanceof Guild) {
            $logger->logInfo(null, Event::GUILD_CREATE, $guild);
        }
    });

    $discord->on(Event::GUILD_UPDATE, function (Guild $guild, Discord $discord, ?Guild $oldGuild) use ($logger) {
        $logger->logInfo(null, Event::GUILD_UPDATE, $guild->getRawAttributes(), $oldGuild?->getRawAttributes());
    });

    $discord->on(Event::GUILD_DELETE, function (object $guild, Discord $discord, bool $unavailable) use ($logger) {
        if (!$unavailable) {
            $logger->logInfo(null, Event::GUILD_DELETE, $guild);
        }
    });

    // Separator

    // Event::GUILD_AUDIT_LOG_ENTRY_CREATE: Results in error

    $discord->on(Event::GUILD_BAN_ADD, function (Ban $ban, Discord $discord) use ($logger) {
        $logger->logInfo(null, Event::GUILD_BAN_ADD, $ban->getRawAttributes());
    });

    $discord->on(Event::GUILD_BAN_REMOVE, function (Ban $ban, Discord $discord) use ($logger) {
        $logger->logInfo(null, Event::GUILD_BAN_REMOVE, $ban->getRawAttributes());
    });

    // Separator

    $discord->on(Event::GUILD_EMOJIS_UPDATE, function (Collection $emojis, Discord $discord, Collection $oldEmojis) use ($logger) {
        $logger->logInfo(null, Event::GUILD_EMOJIS_UPDATE, $emojis, $oldEmojis);
    });

    $discord->on(Event::GUILD_STICKERS_UPDATE, function (Collection $stickers, Discord $discord, Collection $oldStickers) use ($logger) {
        $logger->logInfo(null, Event::GUILD_STICKERS_UPDATE, $stickers, $oldStickers);
    });

    // Separator

    // Event::GUILD_MEMBER_UPDATE: Results in error
    // Event::GUILD_MEMBER_REMOVE: Results in error
    // Event::GUILD_MEMBER_UPDATE: Results in error

    $discord->on(Event::GUILD_MEMBER_ADD, function (Member $member, Discord $discord) use ($logger, $discordBot) {
        foreach ($discordBot->getPlans() as $plan) {
            $plan->welcome($discord, $member->guild_id, $member->id);
        }
        $logger->logInfo($member->id, Event::GUILD_MEMBER_ADD, $member->getRawAttributes());
    });

    // Separator

    $discord->on(Event::GUILD_ROLE_CREATE, function (Role $role, Discord $discord) use ($logger) {
        $logger->logInfo(null, Event::GUILD_ROLE_CREATE, $role->getRawAttributes());
    });

    $discord->on(Event::GUILD_ROLE_UPDATE, function (Role $role, Discord $discord, ?Role $oldRole) use ($logger) {
        $logger->logInfo(null, Event::GUILD_ROLE_UPDATE, $role->getRawAttributes(), $oldRole?->getRawAttributes());
    });

    $discord->on(Event::GUILD_ROLE_DELETE, function (object $role, Discord $discord) use ($logger) {
        $logger->logInfo(null, Event::GUILD_ROLE_DELETE, $role);
    });

    // Separator

    $discord->on(Event::GUILD_SCHEDULED_EVENT_CREATE, function (ScheduledEvent $scheduledEvent, Discord $discord) use ($logger) {
        $logger->logInfo($scheduledEvent->creator_id, Event::GUILD_SCHEDULED_EVENT_CREATE, $scheduledEvent->getRawAttributes());
    });

    $discord->on(Event::GUILD_SCHEDULED_EVENT_UPDATE, function (ScheduledEvent $scheduledEvent, Discord $discord, ?ScheduledEvent $oldScheduledEvent) use ($logger) {
        $logger->logInfo(null, Event::GUILD_SCHEDULED_EVENT_UPDATE, $scheduledEvent->getRawAttributes(), $oldScheduledEvent?->getRawAttributes());
    });

    $discord->on(Event::GUILD_SCHEDULED_EVENT_DELETE, function (ScheduledEvent $scheduledEvent, Discord $discord) use ($logger) {
        $logger->logInfo(null, Event::GUILD_SCHEDULED_EVENT_DELETE, $scheduledEvent->getRawAttributes());
    });

    $discord->on(Event::GUILD_SCHEDULED_EVENT_USER_ADD, function ($data, Discord $discord) use ($logger) {
        $logger->logInfo(null, Event::GUILD_SCHEDULED_EVENT_USER_ADD, $data);
    });

    $discord->on(Event::GUILD_SCHEDULED_EVENT_USER_REMOVE, function ($data, Discord $discord) use ($logger) {
        $logger->logInfo(null, Event::GUILD_SCHEDULED_EVENT_USER_REMOVE, $data);
    });

    // Separator

    $discord->on(Event::GUILD_INTEGRATIONS_UPDATE, function (object $guild, Discord $discord) use ($logger) {
        $logger->logInfo(null, Event::GUILD_INTEGRATIONS_UPDATE, $guild);
    });

    $discord->on(Event::INTEGRATION_CREATE, function (Integration $integration, Discord $discord) use ($logger) {
        $logger->logInfo(null, Event::INTEGRATION_CREATE, $integration->getRawAttributes());
    });

    $discord->on(Event::INTEGRATION_UPDATE, function (Integration $integration, Discord $discord, ?Integration $oldIntegration) use ($logger) {
        $logger->logInfo(null, Event::INTEGRATION_UPDATE, $integration->getRawAttributes(), $oldIntegration?->getRawAttributes());
    });

    $discord->on(Event::INTEGRATION_DELETE, function (object $integration, Discord $discord) use ($logger) {
        $logger->logInfo(null, Event::INTEGRATION_DELETE, $integration);
    });

    // Separator

    $discord->on(Event::INVITE_CREATE, function (Invite $invite, Discord $discord) use ($logger) {
        $logger->logInfo($invite->inviter->id, Event::INVITE_CREATE, $invite->getRawAttributes());
    });

    $discord->on(Event::INVITE_DELETE, function (object $invite, Discord $discord) use ($logger) {
        $logger->logInfo(null, Event::INVITE_DELETE, $invite);
    });

    // Separator

    $discord->on(Event::INTERACTION_CREATE, function (Interaction $interaction, Discord $discord) use ($logger) {
        $logger->logInfo($interaction->user->id, Event::INTERACTION_CREATE, $interaction->getRawAttributes());
    });

    // Separator

    $discord->on(Event::MESSAGE_REACTION_ADD, function (MessageReaction $reaction, Discord $discord) use ($logger) {
        $logger->logInfo($reaction->user_id, Event::MESSAGE_REACTION_ADD, $reaction->getRawAttributes());
    });

    $discord->on(Event::MESSAGE_REACTION_REMOVE, function (MessageReaction $reaction, Discord $discord) use ($logger) {
        $logger->logInfo(null, Event::MESSAGE_REACTION_REMOVE, $reaction->getRawAttributes());
    });

    $discord->on(Event::MESSAGE_REACTION_REMOVE_ALL, function (MessageReaction $reaction, Discord $discord) use ($logger) {
        $logger->logInfo(null, Event::MESSAGE_REACTION_REMOVE_ALL, $reaction->getRawAttributes());
    });

    $discord->on(Event::MESSAGE_REACTION_REMOVE_EMOJI, function (MessageReaction $reaction, Discord $discord) use ($logger) {
        $logger->logInfo(null, Event::MESSAGE_REACTION_REMOVE_EMOJI, $reaction->getRawAttributes());
    });

    // Separator

    // PRESENCE_UPDATE: Too many events and few useful information

    $discord->on(Event::TYPING_START, function (TypingStart $typing, Discord $discord) use ($logger) {
        $logger->logInfo($typing->user_id, Event::TYPING_START, $typing->getRawAttributes());
    });

    $discord->on(Event::USER_UPDATE, function (User $user, Discord $discord, ?User $oldUser) use ($logger) {
        $logger->logInfo(null, Event::USER_UPDATE, $user->getRawAttributes(), $oldUser?->getRawAttributes());
    });

    // Separator

    $discord->on(Event::STAGE_INSTANCE_CREATE, function (StageInstance $stageInstance, Discord $discord) use ($logger) {
        $logger->logInfo(null, Event::STAGE_INSTANCE_CREATE, $stageInstance->getRawAttributes());
    });

    $discord->on(Event::STAGE_INSTANCE_UPDATE, function (StageInstance $stageInstance, Discord $discord, ?StageInstance $oldStageInstance) use ($logger) {
        $logger->logInfo(null, Event::STAGE_INSTANCE_UPDATE, $stageInstance->getRawAttributes(), $oldStageInstance?->getRawAttributes());
    });

    $discord->on(Event::STAGE_INSTANCE_DELETE, function (StageInstance $stageInstance, Discord $discord) use ($logger) {
        $logger->logInfo(null, Event::STAGE_INSTANCE_DELETE, $stageInstance->getRawAttributes());
    });

    // Separator

    $discord->on(Event::VOICE_STATE_UPDATE, function (VoiceStateUpdate $state, Discord $discord, $oldstate) use ($logger) {
        $logger->logInfo($state->user_id, Event::VOICE_STATE_UPDATE, $state->getRawAttributes(), $oldstate);
    });

    $discord->on(Event::VOICE_SERVER_UPDATE, function (VoiceServerUpdate $guild, Discord $discord) use ($logger) {
        $logger->logInfo(null, Event::VOICE_SERVER_UPDATE, $guild->getRawAttributes());
    });

    // Separator

    $discord->on(Event::WEBHOOKS_UPDATE, function (object $guild, Discord $discord, object $channel) use ($logger) {
        $logger->logInfo(null, Event::WEBHOOKS_UPDATE, $channel);
    });
});

$discord->run();
