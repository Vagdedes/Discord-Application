<?php
require '/root/discord_bot/utilities/utilities.php';
$token = get_keys_from_file(
    "discord_token"
    . (!isset($argv[1]) || empty($argv[1]) ? "_0" : "_" . $argv[1])
);

if ($token === null) {
    exit("No Discord token found");
}
ini_set('memory_limit', '-1');
require '/root/vendor/autoload.php';

require '/root/discord_bot/utilities/memory/init.php';
require '/root/discord_bot/utilities/sql.php';
require '/root/discord_bot/utilities/communication.php';
require '/root/discord_bot/utilities/evaluator.php';

require '/root/discord_bot/discord/other/DiscordMute.php';
require '/root/discord_bot/discord/other/DiscordFAQ.php';
require '/root/discord_bot/discord/other/DiscordInviteTracker.php';
require '/root/discord_bot/discord/other/DiscordCommands.php';

require '/root/discord_bot/discord/roles/DiscordJoinRoles.php';
require '/root/discord_bot/discord/roles/DiscordInteractionRoles.php';

require '/root/discord_bot/discord/user/DiscordUserNotes.php';
require '/root/discord_bot/discord/user/DiscordUserTickets.php';
require '/root/discord_bot/discord/user/DiscordUserTargets.php';
require '/root/discord_bot/discord/user/DiscordUserLevels.php';
require '/root/discord_bot/discord/user/DiscordUserQuestionnaire.php';

require '/root/discord_bot/discord/channel/DiscordAntiExpirationThreads.php';
require '/root/discord_bot/discord/channel/DiscordStatisticsChannels.php';
require '/root/discord_bot/discord/channel/DiscordCountingChannels.php';
require '/root/discord_bot/discord/channel/DiscordTemporaryChannels.php';
require '/root/discord_bot/discord/channel/DiscordObjectiveChannels.php';

require '/root/discord_bot/discord/message/DiscordTransferredMessages.php';
require '/root/discord_bot/discord/message/DiscordPersistentMessages.php';
require '/root/discord_bot/discord/message/DiscordAIMessages.php';
require '/root/discord_bot/discord/message/DiscordStatusMessages.php';
require '/root/discord_bot/discord/message/DiscordReminderMessages.php';
require '/root/discord_bot/discord/message/DiscordChatFilteredMessages.php';
require '/root/discord_bot/discord/message/DiscordNotificationMessages.php';

require '/root/discord_bot/discord/helpers/DiscordLogs.php';
require '/root/discord_bot/discord/helpers/DiscordUtilities.php';
require '/root/discord_bot/discord/helpers/DiscordBot.php';
require '/root/discord_bot/discord/helpers/variables.php';
require '/root/discord_bot/discord/helpers/DiscordPermissions.php';
require '/root/discord_bot/discord/helpers/DiscordChannels.php';
require '/root/discord_bot/discord/helpers/DiscordInstructions.php';
require '/root/discord_bot/discord/helpers/DiscordListener.php';
require '/root/discord_bot/discord/helpers/DiscordComponent.php';

use Discord\Discord;
use Discord\Helpers\Collection;
use Discord\Parts\Channel\Channel;
use Discord\Parts\Channel\Invite;
use Discord\Parts\Channel\Message;
use Discord\Parts\Channel\StageInstance;
use Discord\Parts\Guild\AutoModeration\Rule;
use Discord\Parts\Guild\Ban;
use Discord\Parts\Guild\Guild;
use Discord\Parts\Guild\Integration;
use Discord\Parts\Guild\Role;
use Discord\Parts\Guild\ScheduledEvent;
use Discord\Parts\Interactions\Interaction;
use Discord\Parts\Thread\Thread;
use Discord\Parts\User\Member;
use Discord\Parts\User\User;
use Discord\Parts\WebSockets\AutoModerationActionExecution;
use Discord\Parts\WebSockets\MessageReaction;
use Discord\Parts\WebSockets\TypingStart;
use Discord\Parts\WebSockets\VoiceServerUpdate;
use Discord\Parts\WebSockets\VoiceStateUpdate;
use Discord\WebSockets\Event;
use Discord\WebSockets\Intents;

$createdDiscordBot = null;
$logger = new DiscordLogs(null);
$files = evaluator::run();

if (!empty($files)) {
    foreach ($files as $path) {
        require $path;
    }
}
global $token;
$discord = new Discord([
    'token' => $token[0],
    'intents' => Intents::getDefaultIntents() | Intents::GUILD_MEMBERS | Intents::GUILD_PRESENCES | Intents::MESSAGE_CONTENT,
    'storeMessages' => true,
    'retrieveBans' => false,
    'loadAllMembers' => true,
    'disabledEvents' => [],
    'dnsConfig' => '1.1.1.1',
]);

$discord->on('ready', function (Discord $discord) {
    global $createdDiscordBot, $logger;
    load_sql_database();
    $botID = $discord->id;
    $createdDiscordBot = new DiscordBot($discord, $botID);
    $logger = new DiscordLogs($createdDiscordBot);

    $discord->on(Event::MESSAGE_CREATE, function (Message $message, Discord $discord) use ($createdDiscordBot, $logger) {
        if ($message->member !== null && $message->guild_id !== null) {
            $createdDiscordBot->tranferredMessages->trackCreation($message);
            $message->channel->getMessageHistory(
                [
                    'limit' => DiscordAIMessages::PAST_MESSAGES_COUNT,
                    'cache' => true
                ]
            )->done(function ($messageHistory)
            use ($createdDiscordBot, $message) {
                $createdDiscordBot->aiMessages->textAssistance(
                    $message,
                    $messageHistory->toArray()
                );

                foreach (array(
                             DiscordUserLevels::CHAT_CHARACTER_POINTS,
                             DiscordUserLevels::ATTACHMENT_POINTS
                         ) as $type) {
                    $createdDiscordBot->userLevels->runLevel(
                        $message->guild_id,
                        $createdDiscordBot->utilities->getChannelOrThread($message->channel),
                        $message->member,
                        $type,
                        $message
                    );
                }
            });
        }
        $logger->logInfo($message->guild, $message->user_id, Event::MESSAGE_CREATE, $message);
    });

    $discord->getLoop()->addPeriodicTimer(1, function () use ($discord, &$createdDiscordBot, &$logger, $botID) {
        if ($createdDiscordBot->refresh()) {
            $createdDiscordBot = new DiscordBot($discord, $botID);
            $logger = new DiscordLogs($createdDiscordBot);
        }

        foreach ($discord->guilds as $guild) {
            $createdDiscordBot->userLevels->trackVoiceChannels($guild);
        }
        $createdDiscordBot->instructions->refreshManagers();
    });

    $discord->on(Event::MESSAGE_DELETE, function (object $message, Discord $discord) use ($logger, $createdDiscordBot) {
        $createdDiscordBot->tranferredMessages->trackDeletion($message);

        if ($createdDiscordBot->countingChannels->ignoreDeletion === 0) {
            $createdDiscordBot->countingChannels->restore($message);
        } else {
            $createdDiscordBot->countingChannels->ignoreDeletion--;
        }
        $createdDiscordBot->objectiveChannels->trackDeletion($message);
        $logger->logInfo($message?->guild_id, null, Event::MESSAGE_DELETE, $message);
    });

    $discord->on(Event::MESSAGE_UPDATE, function (Message $message, Discord $discord) use ($logger, $createdDiscordBot) {
        $createdDiscordBot->tranferredMessages->trackModification($message);

        if ($createdDiscordBot->countingChannels->ignoreDeletion === 0) {
            $createdDiscordBot->countingChannels->moderate($message);
        } else {
            $createdDiscordBot->countingChannels->ignoreDeletion--;
        }
        $createdDiscordBot->objectiveChannels->trackModification($message);
        $logger->logInfo($message->guild, $message->user_id, Event::MESSAGE_UPDATE, $message);
    });

    // Event::MESSAGE_DELETE_BULK: Results in error

    // Separator

    $discord->on(Event::APPLICATION_COMMAND_PERMISSIONS_UPDATE, function (mixed $commandPermission, Discord $discord, mixed $oldCommandPermission) use ($logger) {
        $logger->logInfo($commandPermission->guild, null, Event::APPLICATION_COMMAND_PERMISSIONS_UPDATE, $commandPermission, $oldCommandPermission);
    });

    // Separator

    $discord->on(Event::AUTO_MODERATION_RULE_CREATE, function (Rule $rule, Discord $discord) use ($logger) {
        $logger->logInfo($rule->guild, $rule->creator->id, Event::AUTO_MODERATION_RULE_CREATE, $rule);
    });

    $discord->on(Event::AUTO_MODERATION_RULE_UPDATE, function (Rule $rule, Discord $discord, ?Rule $oldRule) use ($logger) {
        $logger->logInfo($rule->guild, null, Event::AUTO_MODERATION_RULE_UPDATE, $rule, $oldRule);
    });

    $discord->on(Event::AUTO_MODERATION_RULE_DELETE, function (Rule $rule, Discord $discord) use ($logger) {
        $logger->logInfo($rule->guild, null, Event::AUTO_MODERATION_RULE_DELETE, $rule);
    });

    $discord->on(Event::AUTO_MODERATION_ACTION_EXECUTION, function (AutoModerationActionExecution $actionExecution, Discord $discord) use ($logger) {
        $logger->logInfo($actionExecution->guild, $actionExecution->user_id, Event::AUTO_MODERATION_ACTION_EXECUTION, $actionExecution);
    });

    // Separator

    $discord->on(Event::CHANNEL_CREATE, function (Channel $channel, Discord $discord) use ($logger, $createdDiscordBot) {
        $createdDiscordBot->statisticsChannels->refresh();
        $logger->logInfo($channel->guild, $channel->parent_id, Event::CHANNEL_CREATE, $channel);
    });

    $discord->on(Event::CHANNEL_UPDATE, function (Channel $channel, Discord $discord, ?Channel $oldChannel) use ($logger, $createdDiscordBot) {
        $createdDiscordBot->statisticsChannels->refresh();
        $logger->logInfo($channel->guild, null, Event::CHANNEL_UPDATE, $channel, $oldChannel);
    });

    $discord->on(Event::CHANNEL_DELETE, function (Channel $channel, Discord $discord) use ($logger, $createdDiscordBot) {
        $createdDiscordBot->statisticsChannels->refresh();

        if ($createdDiscordBot->userTickets->ignoreDeletion === 0) {
            $createdDiscordBot->userTickets->closeByChannel($channel, null, null, false);
        } else {
            $createdDiscordBot->userTickets->ignoreDeletion--;
        }
        if ($createdDiscordBot->userTargets->ignoreChannelDeletion === 0) {
            $createdDiscordBot->userTargets->closeByChannelOrThread($channel, null, null, false);
        } else {
            $createdDiscordBot->userTargets->ignoreChannelDeletion--;
        }
        if ($createdDiscordBot->userQuestionnaire->ignoreChannelDeletion === 0) {
            $createdDiscordBot->userQuestionnaire->closeByChannelOrThread($channel, null, null, null, false);
        } else {
            $createdDiscordBot->userQuestionnaire->ignoreChannelDeletion--;
        }
        if ($createdDiscordBot->temporaryChannels->ignoreDeletion === 0) {
            $createdDiscordBot->temporaryChannels->closeByChannel($channel, false);
        } else {
            $createdDiscordBot->temporaryChannels->ignoreDeletion--;
        }
        $logger->logInfo($channel->guild, null, Event::CHANNEL_DELETE, $channel);
    });

    $discord->on(Event::CHANNEL_PINS_UPDATE, function ($pins, Discord $discord) use ($logger) {
        $logger->logInfo($pins?->guild, null, Event::CHANNEL_PINS_UPDATE, $pins);
    });

    // Separator

    $discord->on(Event::THREAD_CREATE, function (Thread $thread, Discord $discord) use ($logger, $createdDiscordBot) {
        $createdDiscordBot->statisticsChannels->refresh();
        $createdDiscordBot->notificationMessages->executeThread($thread);
        $logger->logInfo($thread->guild, $thread->owner_id, Event::THREAD_CREATE, $thread);
    });

    $discord->on(Event::THREAD_UPDATE, function (Thread $thread, Discord $discord, ?Thread $oldThread) use ($logger) {
        $logger->logInfo($thread->guild, null, Event::THREAD_UPDATE, $thread, $oldThread);
    });

    $discord->on(Event::THREAD_DELETE, function (object $thread, Discord $discord) use ($logger, $createdDiscordBot) {
        $createdDiscordBot->statisticsChannels->refresh();

        if ($thread instanceof Thread) {
            if ($createdDiscordBot->userTargets->ignoreThreadDeletion === 0) {
                $createdDiscordBot->userTargets->closeByChannelOrThread($thread->parent);
            } else {
                $createdDiscordBot->userTargets->ignoreThreadDeletion--;
            }
            if ($createdDiscordBot->userQuestionnaire->ignoreThreadDeletion === 0) {
                $createdDiscordBot->userQuestionnaire->closeByChannelOrThread($thread->parent);
            } else {
                $createdDiscordBot->userQuestionnaire->ignoreThreadDeletion--;
            }
            $logger->logInfo($thread->guild, null, Event::THREAD_DELETE, $thread);
        } else {
            $logger->logInfo(null, null, Event::THREAD_DELETE, $thread);
        }
    });

    $discord->on(Event::THREAD_LIST_SYNC, function (Collection $threads, Discord $discord) use ($logger) {
        $logger->logInfo($threads->first()?->guild, null, Event::THREAD_LIST_SYNC, $threads);
    });

    $discord->on(Event::THREAD_MEMBER_UPDATE, function (object $threadMember, Discord $discord) use ($logger) {
        $logger->logInfo($threadMember?->guild, null, Event::THREAD_MEMBER_UPDATE, $threadMember);
    });

    $discord->on(Event::THREAD_MEMBERS_UPDATE, function (Thread $thread, Discord $discord) use ($logger) {
        $logger->logInfo($thread->guild, null, Event::THREAD_MEMBERS_UPDATE, $thread);
    });

    // Separator

    $discord->on(Event::GUILD_CREATE, function (object $guild, Discord $discord) use ($botID, &$logger, &$createdDiscordBot) {
        $logger->logInfo(null, null, Event::GUILD_CREATE, $guild);
        $createdDiscordBot = new DiscordBot($discord, $botID);
        $logger = new DiscordLogs($createdDiscordBot);
    });

    $discord->on(Event::GUILD_UPDATE, function (Guild $guild, Discord $discord, ?Guild $oldGuild) use ($logger) {
        $logger->logInfo($guild, null, Event::GUILD_UPDATE, $guild, $oldGuild);
    });

    $discord->on(Event::GUILD_DELETE, function (object $guild, Discord $discord, bool $unavailable) use ($logger) {
        if (!$unavailable) {
            if ($guild instanceof Guild) {
                $logger->logInfo($guild, null, Event::GUILD_DELETE, $guild);
            } else {
                $logger->logInfo(null, null, Event::GUILD_DELETE, $guild);
            }
        }
    });

    // Separator

    // Event::GUILD_AUDIT_LOG_ENTRY_CREATE: Results in error

    $discord->on(Event::GUILD_BAN_ADD, function (Ban $ban, Discord $discord) use ($logger) {
        $logger->logInfo($ban->guild, null, Event::GUILD_BAN_ADD, $ban);
    });

    $discord->on(Event::GUILD_BAN_REMOVE, function (Ban $ban, Discord $discord) use ($logger) {
        $logger->logInfo($ban->guild, null, Event::GUILD_BAN_REMOVE, $ban);
    });

    // Separator

    $discord->on(Event::GUILD_EMOJIS_UPDATE, function (Collection $emojis, Discord $discord, Collection $oldEmojis) use ($logger) {
        $logger->logInfo($emojis->first()?->guild, null, Event::GUILD_EMOJIS_UPDATE, $emojis->toArray(), $oldEmojis->toArray());
    });

    $discord->on(Event::GUILD_STICKERS_UPDATE, function (Collection $stickers, Discord $discord, Collection $oldStickers) use ($logger) {
        $logger->logInfo($stickers->first()?->guild, null, Event::GUILD_STICKERS_UPDATE, $stickers->toArray(), $oldStickers->toArray());
    });

    // Separator

    // Event::GUILD_MEMBER_UPDATE: Results in error

    $discord->on(Event::GUILD_MEMBER_REMOVE, function (mixed $member, Discord $discord) use ($logger, $createdDiscordBot) {
        $createdDiscordBot->statisticsChannels->refresh();

        if ($member instanceof Member) {
            $createdDiscordBot->statusMessages->run($member, DiscordStatusMessages::GOODBYE);
            $logger->logInfo($member->guild, $member->id, Event::GUILD_MEMBER_REMOVE, $member);
        } else {
            $logger->logInfo($member?->guild, null, Event::GUILD_MEMBER_REMOVE, $member);
        }
    });

    $discord->on(Event::GUILD_MEMBER_ADD, function (Member $member, Discord $discord) use ($logger, $createdDiscordBot) {
        DiscordInviteTracker::track($createdDiscordBot, $member->guild);
        $createdDiscordBot->joinRoles->run($member);
        $createdDiscordBot->statisticsChannels->refresh();
        $createdDiscordBot->statusMessages->run($member, DiscordStatusMessages::WELCOME);
        $logger->logInfo($member->guild, $member->id, Event::GUILD_MEMBER_ADD, $member);
        $logger->logInfo($member->guild, $member->id, DiscordLogs::GUILD_MEMBER_ADD_VIA_INVITE, $member);
    });

    // Separator

    $discord->on(Event::GUILD_ROLE_CREATE, function (Role $role, Discord $discord) use ($logger, $createdDiscordBot) {
        $createdDiscordBot->statisticsChannels->refresh();
        $logger->logInfo($role->guild, null, Event::GUILD_ROLE_CREATE, $role);
    });

    $discord->on(Event::GUILD_ROLE_UPDATE, function (Role $role, Discord $discord, ?Role $oldRole) use ($logger) {
        $logger->logInfo($role->guild, null, Event::GUILD_ROLE_UPDATE, $role, $oldRole);
    });

    $discord->on(Event::GUILD_ROLE_DELETE, function (object $role, Discord $discord) use ($logger, $createdDiscordBot) {
        $createdDiscordBot->statisticsChannels->refresh();

        if ($role instanceof Role) {
            $logger->logInfo($role->guild, null, Event::GUILD_ROLE_DELETE, $role);
        } else {
            $logger->logInfo(null, null, Event::GUILD_ROLE_DELETE, $role);
        }
    });

    // Separator

    $discord->on(Event::GUILD_SCHEDULED_EVENT_CREATE, function (ScheduledEvent $scheduledEvent, Discord $discord) use ($logger) {
        $logger->logInfo($scheduledEvent->guild, $scheduledEvent->creator_id, Event::GUILD_SCHEDULED_EVENT_CREATE, $scheduledEvent);
    });

    $discord->on(Event::GUILD_SCHEDULED_EVENT_UPDATE, function (ScheduledEvent $scheduledEvent, Discord $discord, ?ScheduledEvent $oldScheduledEvent) use ($logger) {
        $logger->logInfo($scheduledEvent->guild, null, Event::GUILD_SCHEDULED_EVENT_UPDATE, $scheduledEvent, $oldScheduledEvent);
    });

    $discord->on(Event::GUILD_SCHEDULED_EVENT_DELETE, function (ScheduledEvent $scheduledEvent, Discord $discord) use ($logger) {
        $logger->logInfo($scheduledEvent->guild, null, Event::GUILD_SCHEDULED_EVENT_DELETE, $scheduledEvent);
    });

    $discord->on(Event::GUILD_SCHEDULED_EVENT_USER_ADD, function (mixed $data, Discord $discord) use ($logger) {
        $logger->logInfo($data?->guild, null, Event::GUILD_SCHEDULED_EVENT_USER_ADD, $data);
    });

    $discord->on(Event::GUILD_SCHEDULED_EVENT_USER_REMOVE, function (mixed $data, Discord $discord) use ($logger) {
        $logger->logInfo($data?->guild, null, Event::GUILD_SCHEDULED_EVENT_USER_REMOVE, $data);
    });

    // Separator

    $discord->on(Event::GUILD_INTEGRATIONS_UPDATE, function (object $guild, Discord $discord) use ($logger) {
        if ($guild instanceof Guild) {
            $logger->logInfo($guild, null, Event::GUILD_INTEGRATIONS_UPDATE, $guild);
        } else {
            $logger->logInfo(null, null, Event::GUILD_INTEGRATIONS_UPDATE, $guild);
        }
    });

    $discord->on(Event::INTEGRATION_CREATE, function (Integration $integration, Discord $discord) use ($logger) {
        $logger->logInfo($integration->guild, null, Event::INTEGRATION_CREATE, $integration);
    });

    $discord->on(Event::INTEGRATION_UPDATE, function (Integration $integration, Discord $discord, ?Integration $oldIntegration) use ($logger) {
        $logger->logInfo($integration->guild, null, Event::INTEGRATION_UPDATE, $integration, $oldIntegration);
    });

    $discord->on(Event::INTEGRATION_DELETE, function (?object $integration, Discord $discord) use ($logger) {
        if ($integration instanceof Integration) {
            $logger->logInfo($integration->guild, null, Event::INTEGRATION_DELETE, $integration);
        } else {
            $logger->logInfo(null, null, Event::INTEGRATION_DELETE, $integration);
        }
    });

    // Separator

    $discord->on(Event::INVITE_CREATE, function (Invite $invite, Discord $discord) use ($logger, $createdDiscordBot) {
        $createdDiscordBot->statisticsChannels->refresh();
        DiscordInviteTracker::track($createdDiscordBot, $invite->guild);
        $logger->logInfo($invite->guild, $invite->inviter->id, Event::INVITE_CREATE, $invite);
    });

    $discord->on(Event::INVITE_DELETE, function (object $invite, Discord $discord) use ($logger, $createdDiscordBot) {
        $createdDiscordBot->statisticsChannels->refresh();
        DiscordInviteTracker::track($createdDiscordBot, $invite->guild);

        if ($invite instanceof Invite) {
            $logger->logInfo($invite->guild, $invite->inviter?->id, Event::INVITE_DELETE, $invite);
        } else {
            $logger->logInfo(null, null, Event::INVITE_DELETE, $invite);
        }
    });

    // Separator

    $discord->on(Event::INTERACTION_CREATE, function (Interaction $interaction, Discord $discord) use ($logger) {
        $logger->logInfo($interaction->guild, $interaction->user->id, Event::INTERACTION_CREATE, $interaction, null);
    });

    // Separator

    $discord->on(Event::MESSAGE_REACTION_ADD, function (MessageReaction $reaction, Discord $discord) use ($logger, $createdDiscordBot) {
        $createdDiscordBot->userLevels->runLevel(
            $reaction->guild_id,
            $createdDiscordBot->utilities->getChannelOrThread($reaction->channel),
            $reaction->member,
            DiscordUserLevels::REACTION_POINTS,
            $reaction
        );
        $createdDiscordBot->component->handleReaction($reaction);
        $logger->logInfo($reaction->guild, $reaction->user_id, Event::MESSAGE_REACTION_ADD, $reaction);
    });

    $discord->on(Event::MESSAGE_REACTION_REMOVE, function (MessageReaction $reaction, Discord $discord) use ($logger) {
        $logger->logInfo($reaction->guild, null, Event::MESSAGE_REACTION_REMOVE, $reaction);
    });

    $discord->on(Event::MESSAGE_REACTION_REMOVE_ALL, function (MessageReaction $reaction, Discord $discord) use ($logger) {
        $logger->logInfo($reaction->guild, null, Event::MESSAGE_REACTION_REMOVE_ALL, $reaction);
    });

    $discord->on(Event::MESSAGE_REACTION_REMOVE_EMOJI, function (MessageReaction $reaction, Discord $discord) use ($logger) {
        $logger->logInfo($reaction->guild, null, Event::MESSAGE_REACTION_REMOVE_EMOJI, $reaction);
    });

    // Separator

    // PRESENCE_UPDATE: Too many events and few useful information

    $discord->on(Event::TYPING_START, function (TypingStart $typing, Discord $discord) use ($logger) {
        $logger->logInfo($typing->guild, $typing->user_id, Event::TYPING_START, $typing);
    });

    $discord->on(Event::USER_UPDATE, function (User $user, Discord $discord, ?User $oldUser) use ($logger) {
        $logger->logInfo(null, null, Event::USER_UPDATE, $user, $oldUser);
    });

    // Separator

    $discord->on(Event::STAGE_INSTANCE_CREATE, function (StageInstance $stageInstance, Discord $discord) use ($logger) {
        $logger->logInfo($stageInstance->guild, null, Event::STAGE_INSTANCE_CREATE, $stageInstance);
    });

    $discord->on(Event::STAGE_INSTANCE_UPDATE, function (StageInstance $stageInstance, Discord $discord, ?StageInstance $oldStageInstance) use ($logger) {
        $logger->logInfo($stageInstance->guild, null, Event::STAGE_INSTANCE_UPDATE, $stageInstance, $oldStageInstance);
    });

    $discord->on(Event::STAGE_INSTANCE_DELETE, function (StageInstance $stageInstance, Discord $discord) use ($logger) {
        $logger->logInfo($stageInstance->guild, null, Event::STAGE_INSTANCE_DELETE, $stageInstance);
    });

    // Separator

    $discord->on(Event::VOICE_STATE_UPDATE, function (VoiceStateUpdate $state, Discord $discord, $oldstate)
    use ($logger, $createdDiscordBot) {
        if ($state->channel_id === null) {
            $createdDiscordBot->temporaryChannels->trackLeave($oldstate);
        } else {
            $mute = $createdDiscordBot->mute->isMuted($state->member, $state->channel, DiscordMute::VOICE);

            if ($mute !== null) {
                $state->channel->muteMember($state->member, $mute->creation_reason);
                $createdDiscordBot->utilities->sendMessageInPieces($state->member, $mute->creation_reason);
            } else if ($createdDiscordBot->mute->wasMuted($state->member, $state->channel, DiscordMute::VOICE)) {
                $state->channel->unmuteMember($state->member);
            }
            if ($oldstate === null) {
                $createdDiscordBot->temporaryChannels->trackJoin($state);
            } else if ($state->channel_id != $oldstate->channel_id) {
                $createdDiscordBot->temporaryChannels->trackLeave($oldstate);
                $createdDiscordBot->temporaryChannels->trackJoin($state);
            }
        }
        $logger->logInfo($state->guild, $state->user_id, Event::VOICE_STATE_UPDATE, $state, $oldstate);
    });

    $discord->on(Event::VOICE_SERVER_UPDATE, function (VoiceServerUpdate $guild, Discord $discord) use ($logger) {
        $logger->logInfo($guild->guild, null, Event::VOICE_SERVER_UPDATE, $guild);
    });

    // Separator

    $discord->on(Event::WEBHOOKS_UPDATE, function (object $guild, Discord $discord, object $channel) use ($logger) {
        if ($guild instanceof Guild) {
            $logger->logInfo($guild, null, Event::WEBHOOKS_UPDATE, $channel);
        } else {
            $logger->logInfo(null, null, Event::WEBHOOKS_UPDATE, $channel);
        }
    });
});

$discord->run();
