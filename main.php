<?php
require '/root/discord_bot/utilities/utilities.php';

$token = get_keys_from_file(
    "/root/discord_bot/private/credentials/discord_token"
    . (!isset($argv[1]) || empty($argv[1]) || $argv[1] == 0 ? "" : "_" . $argv[1])
);

if ($token === null) {
    exit("No Discord token found");
}
ini_set('memory_limit', '-1');
require '/root/vendor/autoload.php';

require '/root/discord_bot/utilities/memory/init.php';
require '/root/discord_bot/utilities/sql.php';
require '/root/discord_bot/utilities/runnable.php';
require '/root/discord_bot/utilities/sql_connection.php';
require '/root/discord_bot/utilities/communication.php';
require '/root/discord_bot/utilities/encrypt.php';

require '/root/discord_bot/web/LoadBalancer.php';

require '/root/discord_bot/discord/other/DiscordCommands.php';
require '/root/discord_bot/discord/other/DiscordInviteTracker.php';
require '/root/discord_bot/discord/other/DiscordSocialAlerts.php';
require '/root/discord_bot/discord/other/DiscordLogs.php';

require '/root/discord_bot/discord/user/DiscordUserTickets.php';
require '/root/discord_bot/discord/user/DiscordUserTargets.php';
require '/root/discord_bot/discord/user/DiscordUserLevels.php';
require '/root/discord_bot/discord/user/DiscordUserNotes.php';
require '/root/discord_bot/discord/user/DiscordUserQuestionnaire.php';
require '/root/discord_bot/discord/user/DiscordUserGiveaways.php';

require '/root/discord_bot/discord/channel/DiscordCountingChannels.php';
require '/root/discord_bot/discord/channel/DiscordTemporaryChannels.php';
require '/root/discord_bot/discord/channel/DiscordStatisticsChannels.php';
require '/root/discord_bot/discord/channel/DiscordAntiExpirationThreads.php';

require '/root/discord_bot/discord/message/DiscordPersistentMessages.php';
require '/root/discord_bot/discord/message/DiscordAIMessages.php';
require '/root/discord_bot/discord/message/DiscordStatusMessages.php';
require '/root/discord_bot/discord/message/DiscordReminderMessages.php';

require '/root/discord_bot/discord/interaction/DiscordInteractionRoles.php';
require '/root/discord_bot/discord/interaction/DiscordPolls.php';

require '/root/discord_bot/discord/helpers/variables.php';
require '/root/discord_bot/discord/helpers/DiscordConversation.php';
require '/root/discord_bot/discord/helpers/DiscordLimits.php';
require '/root/discord_bot/discord/helpers/DiscordPlan.php';
require '/root/discord_bot/discord/helpers/DiscordInstructions.php';
require '/root/discord_bot/discord/helpers/DiscordBot.php';
require '/root/discord_bot/discord/helpers/DiscordListener.php';
require '/root/discord_bot/discord/helpers/DiscordUtilities.php';
require '/root/discord_bot/discord/helpers/DiscordPermissions.php';
require '/root/discord_bot/discord/helpers/DiscordModeration.php';
require '/root/discord_bot/discord/helpers/DiscordComponent.php';
require '/root/discord_bot/discord/helpers/DiscordCurrency.php';
require '/root/discord_bot/discord/helpers/DiscordLocations.php';

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
$files = LoadBalancer::getFiles(
    array(
        "/var/www/.structure/library/account",
        "/var/www/.structure/library/polymart",
        "/var/www/.structure/library/patreon",
        "/var/www/.structure/library/paypal",
        "/var/www/.structure/library/discord",
        "/var/www/.structure/library/stripe",
        "/var/www/.structure/library/builtbybit",
        "/var/www/.structure/library/phone",
        "/var/www/.structure/library/email",
        "/var/www/.structure/library/gameCloud"
    )
);

if (!empty($files)) {
    foreach ($files as $fileName => $file) {
        try {
            eval($file);
        } catch (Throwable $error) {
            $logger->logError(null, $file . ": " . $error->getMessage());
        }
    }
    $email_credentials_directory = "/root/discord_bot/private/credentials/email_credentials";
    $patreon2_credentials_directory = "/root/discord_bot/private/credentials/patreon_2_credentials";
}

function initiate_discord_bot(): void
{
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
        global $logger, $createdDiscordBot;
        load_sql_database();
        $botID = $discord->id;

        if (!empty($discord->guilds->toArray())) {
            foreach ($discord->guilds as $guild) {
                if ($guild instanceof Guild) {
                    $member = $guild->members->toArray()[$botID];

                    if ($member instanceof Member) {
                        if (empty($member->roles->toArray())) {
                            $guild->leave();
                            $logger->logError(null, "Bot left guild " . $guild->id . " because it has no roles and therefore no permissions.");
                        } else {
                            $admin = false;

                            foreach ($member->roles as $role) {
                                if ($role->permissions->administrator) {
                                    $admin = true;
                                    break;
                                }
                            }

                            if (!$admin) {
                                $guild->leave();
                                $logger->logError(null, "Bot left guild " . $guild->id . " because it has no administrator permissions.");
                            }
                        }
                    }
                }
            }
        }
        $createdDiscordBot = new DiscordBot($discord, $botID);
        $logger = new DiscordLogs($createdDiscordBot);

        $discord->on(Event::MESSAGE_CREATE, function (Message $message, Discord $discord) use ($createdDiscordBot, $logger) {
            $ai = $message->member !== null;
            $userLevel = $ai && $message->guild_id !== null;

            if ($ai || $userLevel) {
                foreach ($createdDiscordBot->plans as $plan) {
                    if ($ai
                        && $plan->aiMessages->textAssistance(
                            $message,
                            $message->member,
                            $message->content,
                        )) {
                        $ai = false;
                    }
                    if ($userLevel) {
                        foreach (array(
                                     DiscordUserLevels::CHAT_CHARACTER_POINTS,
                                     DiscordUserLevels::ATTACHMENT_POINTS
                                 ) as $type) {
                            $plan->userLevels->runLevel(
                                $message->guild_id,
                                $createdDiscordBot->utilities->getChannel($message->channel),
                                $message->member,
                                $type,
                                $message
                            );
                        }
                    }
                }
            }
            $logger->logInfo($message->guild, $message->user_id, Event::MESSAGE_CREATE, $message->getRawAttributes());
        });

        $discord->on(Event::MESSAGE_DELETE, function (object $message, Discord $discord) use ($logger, $createdDiscordBot) {
            if ($message instanceof Message) {
                foreach ($createdDiscordBot->plans as $plan) {
                    if ($plan->countingChannels->ignoreDeletion === 0) {
                        if ($plan->countingChannels->restore($message)) {
                            break;
                        }
                    } else {
                        $plan->countingChannels->ignoreDeletion--;
                    }
                }
                $logger->logInfo($message->guild, null, Event::MESSAGE_DELETE, $message);
            } else {
                $logger->logInfo(null, null, Event::MESSAGE_DELETE, $message);
            }
        });

        $discord->on(Event::MESSAGE_UPDATE, function (Message $message, Discord $discord) use ($logger, $createdDiscordBot) {
            foreach ($createdDiscordBot->plans as $plan) {
                if ($plan->countingChannels->ignoreDeletion === 0) {
                    if ($plan->countingChannels->moderate($message)) {
                        break;
                    }
                } else {
                    $plan->countingChannels->ignoreDeletion--;
                }
            }
            $logger->logInfo($message->guild, $message->user_id, Event::MESSAGE_UPDATE, $message->getRawAttributes());
        });

        // Event::MESSAGE_DELETE_BULK: Results in error

        // Separator

        $discord->on(Event::APPLICATION_COMMAND_PERMISSIONS_UPDATE, function (CommandPermissions $commandPermission, Discord $discord, ?CommandPermissions $oldCommandPermission) use ($logger) {
            $logger->logInfo($commandPermission->guild, null, Event::APPLICATION_COMMAND_PERMISSIONS_UPDATE, $commandPermission->getRawAttributes(), $oldCommandPermission?->getRawAttributes());
        });

        // Separator

        $discord->on(Event::AUTO_MODERATION_RULE_CREATE, function (Rule $rule, Discord $discord) use ($logger) {
            $logger->logInfo($rule->guild, $rule->creator->id, Event::AUTO_MODERATION_RULE_CREATE, $rule->getRawAttributes());
        });

        $discord->on(Event::AUTO_MODERATION_RULE_UPDATE, function (Rule $rule, Discord $discord, ?Rule $oldRule) use ($logger) {
            $logger->logInfo($rule->guild, null, Event::AUTO_MODERATION_RULE_UPDATE, $rule->getRawAttributes(), $oldRule?->getRawAttributes());
        });

        $discord->on(Event::AUTO_MODERATION_RULE_DELETE, function (Rule $rule, Discord $discord) use ($logger) {
            $logger->logInfo($rule->guild, null, Event::AUTO_MODERATION_RULE_DELETE, $rule->getRawAttributes());
        });

        $discord->on(Event::AUTO_MODERATION_ACTION_EXECUTION, function (AutoModerationActionExecution $actionExecution, Discord $discord) use ($logger) {
            $logger->logInfo($actionExecution->guild, $actionExecution->user_id, Event::AUTO_MODERATION_ACTION_EXECUTION, $actionExecution->getRawAttributes());
        });

        // Separator

        $discord->on(Event::CHANNEL_CREATE, function (Channel $channel, Discord $discord) use ($logger, $createdDiscordBot) {
            foreach ($createdDiscordBot->plans as $plan) {
                $plan->statisticsChannels->refresh();
            }
            $logger->logInfo($channel->guild, $channel->parent_id, Event::CHANNEL_CREATE, $channel->getRawAttributes());
        });

        $discord->on(Event::CHANNEL_UPDATE, function (Channel $channel, Discord $discord, ?Channel $oldChannel) use ($logger, $createdDiscordBot) {
            foreach ($createdDiscordBot->plans as $plan) {
                $plan->statisticsChannels->refresh();
            }
            $logger->logInfo($channel->guild, null, Event::CHANNEL_UPDATE, $channel->getRawAttributes(), $oldChannel?->getRawAttributes());
        });

        $discord->on(Event::CHANNEL_DELETE, function (Channel $channel, Discord $discord) use ($logger, $createdDiscordBot) {
            foreach ($createdDiscordBot->plans as $plan) {
                $plan->statisticsChannels->refresh();

                if ($plan->userTickets->ignoreDeletion === 0) {
                    $plan->userTickets->closeByChannel($channel);
                } else {
                    $plan->userTickets->ignoreDeletion--;
                }
                if ($plan->userTargets->ignoreChannelDeletion === 0) {
                    $plan->userTargets->closeByChannelOrThread($channel);
                } else {
                    $plan->userTargets->ignoreChannelDeletion--;
                }
            }
            $logger->logInfo($channel->guild, null, Event::CHANNEL_DELETE, $channel->getRawAttributes());
        });

        $discord->on(Event::CHANNEL_PINS_UPDATE, function ($pins, Discord $discord) use ($logger) {
            $logger->logInfo($pins?->guild, null, Event::CHANNEL_PINS_UPDATE, $pins);
        });

        // Separator

        $discord->on(Event::THREAD_CREATE, function (Thread $thread, Discord $discord) use ($logger, $createdDiscordBot) {
            foreach ($createdDiscordBot->plans as $plan) {
                $plan->statisticsChannels->refresh();
            }
            $logger->logInfo($thread->guild, null, Event::THREAD_CREATE, $thread);
        });

        $discord->on(Event::THREAD_UPDATE, function (Thread $thread, Discord $discord, ?Thread $oldThread) use ($logger) {
            $logger->logInfo($thread->guild, null, Event::THREAD_UPDATE, $thread, $oldThread);
        });

        $discord->on(Event::THREAD_DELETE, function (object $thread, Discord $discord) use ($logger, $createdDiscordBot) {
            if ($thread instanceof Thread) {
                foreach ($createdDiscordBot->plans as $plan) {
                    $plan->statisticsChannels->refresh();

                    if ($plan->userTargets->ignoreThreadDeletion === 0) {
                        $plan->userTargets->closeByChannelOrThread($thread->parent);
                    } else {
                        $plan->userTargets->ignoreThreadDeletion--;
                    }
                }
                $logger->logInfo($thread->guild, null, Event::THREAD_DELETE, $thread);
            } else {
                foreach ($createdDiscordBot->plans as $plan) {
                    $plan->statisticsChannels->refresh();
                }
                $logger->logInfo(null, null, Event::THREAD_DELETE, $thread);
            }
        });

        $discord->on(Event::THREAD_LIST_SYNC, function (Collection $threads, Discord $discord) use ($logger) {
            $logger->logInfo($threads->first()?->guild, null, Event::THREAD_LIST_SYNC, $threads);
        });

        $discord->on(Event::THREAD_MEMBER_UPDATE, function (object $threadMember, Discord $discord) use ($logger) {
            $logger->logInfo($threadMember?->guild, null, Event::THREAD_MEMBER_UPDATE, $threadMember->getRawAttributes());
        });

        $discord->on(Event::THREAD_MEMBERS_UPDATE, function (Thread $thread, Discord $discord) use ($logger) {
            $logger->logInfo($thread->guild, null, Event::THREAD_MEMBERS_UPDATE, $thread);
        });

        // Separator

        $discord->on(Event::GUILD_CREATE, function (object $guild, Discord $discord) use ($logger, $createdDiscordBot) {
            if ($guild instanceof Guild) {
                if (!$logger->logInfo($guild, null, Event::GUILD_CREATE, $guild)) {
                    $createdDiscordBot->refresh(true);
                }
            } else {
                $logger->logInfo(null, null, Event::GUILD_CREATE, $guild);
            }
        });

        $discord->on(Event::GUILD_UPDATE, function (Guild $guild, Discord $discord, ?Guild $oldGuild) use ($logger) {
            $logger->logInfo($guild, null, Event::GUILD_UPDATE, $guild->getRawAttributes(), $oldGuild?->getRawAttributes());
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
            $logger->logInfo($ban->guild, null, Event::GUILD_BAN_ADD, $ban->getRawAttributes());
        });

        $discord->on(Event::GUILD_BAN_REMOVE, function (Ban $ban, Discord $discord) use ($logger) {
            $logger->logInfo($ban->guild, null, Event::GUILD_BAN_REMOVE, $ban->getRawAttributes());
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
            if ($member instanceof Member) {
                foreach ($createdDiscordBot->plans as $plan) {
                    $plan->statusMessages->goodbye($member);
                    $plan->statisticsChannels->refresh();
                }
                $logger->logInfo($member->guild, $member->id, Event::GUILD_MEMBER_ADD, $member->getRawAttributes());
            } else {
                $logger->logInfo($member?->guild, null, Event::GUILD_MEMBER_ADD, $member);
            }
        });

        $discord->on(Event::GUILD_MEMBER_ADD, function (Member $member, Discord $discord) use ($logger, $createdDiscordBot) {
            foreach ($createdDiscordBot->plans as $plan) {
                $plan->statusMessages->welcome($member);
                $plan->statisticsChannels->refresh();
            }
            $logger->logInfo($member->guild, $member->id, Event::GUILD_MEMBER_ADD, $member->getRawAttributes());
        });

        // Separator

        $discord->on(Event::GUILD_ROLE_CREATE, function (Role $role, Discord $discord) use ($logger, $createdDiscordBot) {
            foreach ($createdDiscordBot->plans as $plan) {
                $plan->statisticsChannels->refresh();
            }
            $logger->logInfo($role->guild, null, Event::GUILD_ROLE_CREATE, $role->getRawAttributes());
        });

        $discord->on(Event::GUILD_ROLE_UPDATE, function (Role $role, Discord $discord, ?Role $oldRole) use ($logger) {
            $logger->logInfo($role->guild, null, Event::GUILD_ROLE_UPDATE, $role->getRawAttributes(), $oldRole?->getRawAttributes());
        });

        $discord->on(Event::GUILD_ROLE_DELETE, function (object $role, Discord $discord) use ($logger, $createdDiscordBot) {
            foreach ($createdDiscordBot->plans as $plan) {
                $plan->statisticsChannels->refresh();
            }
            if ($role instanceof Role) {
                $logger->logInfo($role->guild, null, Event::GUILD_ROLE_DELETE, $role);
            } else {
                $logger->logInfo(null, null, Event::GUILD_ROLE_DELETE, $role);
            }
        });

        // Separator

        $discord->on(Event::GUILD_SCHEDULED_EVENT_CREATE, function (ScheduledEvent $scheduledEvent, Discord $discord) use ($logger) {
            $logger->logInfo($scheduledEvent->guild, $scheduledEvent->creator_id, Event::GUILD_SCHEDULED_EVENT_CREATE, $scheduledEvent->getRawAttributes());
        });

        $discord->on(Event::GUILD_SCHEDULED_EVENT_UPDATE, function (ScheduledEvent $scheduledEvent, Discord $discord, ?ScheduledEvent $oldScheduledEvent) use ($logger) {
            $logger->logInfo($scheduledEvent->guild, null, Event::GUILD_SCHEDULED_EVENT_UPDATE, $scheduledEvent->getRawAttributes(), $oldScheduledEvent?->getRawAttributes());
        });

        $discord->on(Event::GUILD_SCHEDULED_EVENT_DELETE, function (ScheduledEvent $scheduledEvent, Discord $discord) use ($logger) {
            $logger->logInfo($scheduledEvent->guild, null, Event::GUILD_SCHEDULED_EVENT_DELETE, $scheduledEvent->getRawAttributes());
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
            $logger->logInfo($integration->guild, null, Event::INTEGRATION_CREATE, $integration->getRawAttributes());
        });

        $discord->on(Event::INTEGRATION_UPDATE, function (Integration $integration, Discord $discord, ?Integration $oldIntegration) use ($logger) {
            $logger->logInfo($integration->guild, null, Event::INTEGRATION_UPDATE, $integration->getRawAttributes(), $oldIntegration?->getRawAttributes());
        });

        $discord->on(Event::INTEGRATION_DELETE, function (object $integration, Discord $discord) use ($logger) {
            if ($integration instanceof Integration) {
                $logger->logInfo($integration->guild, null, Event::INTEGRATION_DELETE, $integration);
            } else {
                $logger->logInfo(null, null, Event::INTEGRATION_DELETE, $integration);
            }
        });

        // Separator

        $discord->on(Event::INVITE_CREATE, function (Invite $invite, Discord $discord) use ($logger, $createdDiscordBot) {
            foreach ($createdDiscordBot->plans as $plan) {
                $plan->inviteTracker->track($invite->guild);
                break; // Break because the data is global
            }
            foreach ($createdDiscordBot->plans as $plan) {
                $plan->statisticsChannels->refresh();
            }
            $logger->logInfo($invite->guild, $invite->inviter->id, Event::INVITE_CREATE, $invite->getRawAttributes());
        });

        $discord->on(Event::INVITE_DELETE, function (object $invite, Discord $discord) use ($logger, $createdDiscordBot) {
            if ($invite instanceof Invite) {
                foreach ($createdDiscordBot->plans as $plan) {
                    $plan->inviteTracker->track($invite->guild);
                    break; // Break because the data is global
                }
                foreach ($createdDiscordBot->plans as $plan) {
                    $plan->statisticsChannels->refresh();
                }
                $logger->logInfo($invite->guild, $invite->inviter->id, Event::INVITE_DELETE, $invite->getRawAttributes());
            } else {
                foreach ($createdDiscordBot->plans as $plan) {
                    $plan->statisticsChannels->refresh();
                }
                $logger->logInfo(null, null, Event::INVITE_DELETE, $invite);
            }
        });

        // Separator

        $discord->on(Event::INTERACTION_CREATE, function (Interaction $interaction, Discord $discord) use ($logger) {
            $logger->logInfo($interaction->guild, $interaction->user->id, Event::INTERACTION_CREATE, $interaction->getRawAttributes(), null, false);
        });

        // Separator

        $discord->on(Event::MESSAGE_REACTION_ADD, function (MessageReaction $reaction, Discord $discord) use ($logger, $createdDiscordBot) {
            foreach ($createdDiscordBot->plans as $plan) {
                $plan->userLevels->runLevel(
                    $reaction->guild_id,
                    $createdDiscordBot->utilities->getChannel($reaction->channel),
                    $reaction->member,
                    DiscordUserLevels::REACTION_POINTS,
                    $reaction
                );
            }
            $logger->logInfo($reaction->guild, $reaction->user_id, Event::MESSAGE_REACTION_ADD, $reaction->getRawAttributes());
        });

        $discord->on(Event::MESSAGE_REACTION_REMOVE, function (MessageReaction $reaction, Discord $discord) use ($logger) {
            $logger->logInfo($reaction->guild, null, Event::MESSAGE_REACTION_REMOVE, $reaction->getRawAttributes());
        });

        $discord->on(Event::MESSAGE_REACTION_REMOVE_ALL, function (MessageReaction $reaction, Discord $discord) use ($logger) {
            $logger->logInfo($reaction->guild, null, Event::MESSAGE_REACTION_REMOVE_ALL, $reaction->getRawAttributes());
        });

        $discord->on(Event::MESSAGE_REACTION_REMOVE_EMOJI, function (MessageReaction $reaction, Discord $discord) use ($logger) {
            $logger->logInfo($reaction->guild, null, Event::MESSAGE_REACTION_REMOVE_EMOJI, $reaction->getRawAttributes());
        });

        // Separator

        // PRESENCE_UPDATE: Too many events and few useful information

        $discord->on(Event::TYPING_START, function (TypingStart $typing, Discord $discord) use ($logger) {
            $logger->logInfo($typing->guild, $typing->user_id, Event::TYPING_START, $typing->getRawAttributes());
        });

        $discord->on(Event::USER_UPDATE, function (User $user, Discord $discord, ?User $oldUser) use ($logger) {
            $logger->logInfo(null, null, Event::USER_UPDATE, $user->getRawAttributes(), $oldUser?->getRawAttributes());
        });

        // Separator

        $discord->on(Event::STAGE_INSTANCE_CREATE, function (StageInstance $stageInstance, Discord $discord) use ($logger) {
            $logger->logInfo($stageInstance->guild, null, Event::STAGE_INSTANCE_CREATE, $stageInstance->getRawAttributes());
        });

        $discord->on(Event::STAGE_INSTANCE_UPDATE, function (StageInstance $stageInstance, Discord $discord, ?StageInstance $oldStageInstance) use ($logger) {
            $logger->logInfo($stageInstance->guild, null, Event::STAGE_INSTANCE_UPDATE, $stageInstance->getRawAttributes(), $oldStageInstance?->getRawAttributes());
        });

        $discord->on(Event::STAGE_INSTANCE_DELETE, function (StageInstance $stageInstance, Discord $discord) use ($logger) {
            $logger->logInfo($stageInstance->guild, null, Event::STAGE_INSTANCE_DELETE, $stageInstance->getRawAttributes());
        });

        // Separator

        $discord->on(Event::VOICE_STATE_UPDATE, function (VoiceStateUpdate $state, Discord $discord, $oldstate) use ($logger) {
            $logger->logInfo($state->guild, $state->user_id, Event::VOICE_STATE_UPDATE, $state->getRawAttributes(), $oldstate);
        });

        $discord->on(Event::VOICE_SERVER_UPDATE, function (VoiceServerUpdate $guild, Discord $discord) use ($logger) {
            $logger->logInfo($guild->guild, null, Event::VOICE_SERVER_UPDATE, $guild->getRawAttributes());
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
}

initiate_discord_bot();
