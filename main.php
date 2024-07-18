<?php
require '/root/discord_bot/utilities/utilities.php';

$token = get_keys_from_file(
    "/root/discord_bot/private/credentials/discord_token"
    . (!isset($argv[1]) || empty($argv[1]) ? "_0" : "_" . $argv[1])
);

if ($token === null) {
    exit("No Discord token found");
}
ini_set('memory_limit', '-1');
require '/root/vendor/autoload.php';

require '/root/discord_bot/utilities/memory/init.php';
require '/root/discord_bot/utilities/sql.php';
require '/root/discord_bot/utilities/sql_connection.php';
require '/root/discord_bot/utilities/communication.php';
require '/root/discord_bot/utilities/LoadBalancer.php';

require '/root/discord_bot/discord/custom/standalone/DiscordLogs.php';
require '/root/discord_bot/discord/custom/standalone/DiscordMute.php';
require '/root/discord_bot/discord/custom/DiscordCommands.php';

require '/root/discord_bot/discord/other/standalone/DiscordWebAttachments.php';
require '/root/discord_bot/discord/other/standalone/DiscordFAQ.php';
require '/root/discord_bot/discord/other/DiscordInviteTracker.php';

require '/root/discord_bot/discord/roles/standalone/DiscordJoinRoles.php';
require '/root/discord_bot/discord/roles/DiscordInteractionRoles.php';

require '/root/discord_bot/discord/user/standalone/DiscordUserNotes.php';
require '/root/discord_bot/discord/user/standalone/DiscordUserGiveaways.php';
require '/root/discord_bot/discord/user/standalone/DiscordUserPolls.php';
require '/root/discord_bot/discord/user/standalone/DiscordUserSuggestions.php';
require '/root/discord_bot/discord/user/standalone/DiscordUserEvents.php';
require '/root/discord_bot/discord/user/DiscordUserTickets.php';
require '/root/discord_bot/discord/user/DiscordUserTargets.php';
require '/root/discord_bot/discord/user/DiscordUserLevels.php';
require '/root/discord_bot/discord/user/DiscordUserQuestionnaire.php';

require '/root/discord_bot/discord/channel/standalone/DiscordAntiExpirationThreads.php';
require '/root/discord_bot/discord/channel/standalone/DiscordStatisticsChannels.php';
require '/root/discord_bot/discord/channel/DiscordCountingChannels.php';
require '/root/discord_bot/discord/channel/DiscordTemporaryChannels.php';
require '/root/discord_bot/discord/channel/DiscordObjectiveChannels.php';

require '/root/discord_bot/discord/message/standalone/DiscordTransferredMessages.php';
require '/root/discord_bot/discord/message/DiscordPersistentMessages.php';
require '/root/discord_bot/discord/message/DiscordAIMessages.php';
require '/root/discord_bot/discord/message/DiscordStatusMessages.php';
require '/root/discord_bot/discord/message/DiscordReminderMessages.php';
require '/root/discord_bot/discord/message/DiscordChatFilteredMessages.php';
require '/root/discord_bot/discord/message/DiscordMessageNotifications.php';

require '/root/discord_bot/discord/helpers/standalone/DiscordUtilities.php';
require '/root/discord_bot/discord/helpers/standalone/DiscordBot.php';
require '/root/discord_bot/discord/helpers/standalone/variables.php';
require '/root/discord_bot/discord/helpers/standalone/DiscordPermissions.php';
require '/root/discord_bot/discord/helpers/DiscordPlan.php';
require '/root/discord_bot/discord/helpers/DiscordInstructions.php';
require '/root/discord_bot/discord/helpers/DiscordListener.php';
require '/root/discord_bot/discord/helpers/DiscordComponent.php';
require '/root/discord_bot/discord/helpers/DiscordChannels.php';

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
        "/var/www/.structure/library/account/api/tasks/panel.php",
        "/var/www/.structure/library/polymart",
        "/var/www/.structure/library/patreon",
        "/var/www/.structure/library/paypal",
        "/var/www/.structure/library/discord",
        "/var/www/.structure/library/stripe",
        "/var/www/.structure/library/builtbybit",
        "/var/www/.structure/library/phone",
        "/var/www/.structure/library/email",
        "/var/www/.structure/library/gameCloud",
        "/var/www/.structure/library/ai",
        "/var/www/.structure/library/base/placeholder.php",
        "/var/www/.structure/library/base/minecraft.php",
        "/var/www/.structure/library/base/encrypt.php",
        "/var/www/.structure/library/base/objects"
    )
);

if (!empty($files)) {
    $total = array();

    foreach ($files as $file) {
        try {
            eval($file);
            $total[] = $file;
        } catch (Throwable $error) {
            $logger->logError(null, $file . ": " . $error->getMessage());
        }
    }
    $file = fopen(
        "/root/discord_bot/evaluated/files.php",
        "w"
    );

    if ($file !== false) {
        fwrite($file, implode("\n", $total));
        fclose($file);
    }
    $email_credentials_directory = "/root/discord_bot/private/credentials/email_credentials";
    $patreon1_credentials_directory = "/root/discord_bot/private/credentials/patreon_1_credentials";
    $patreon2_credentials_directory = "/root/discord_bot/private/credentials/patreon_2_credentials";
    $builtbybit_credentials_directory = "/root/discord_bot/private/credentials/builtbybit_credentials";
    $polymart_credentials_directory = "/root/discord_bot/private/credentials/polymart_credentials";
    $twilio_credentials_directory = "/root/discord_bot/private/credentials/twilio_credentials";
    $stripe_credentials_directory = "/root/discord_bot/private/credentials/stripe_credentials";
    $paypal_credentials_directory = "/root/discord_bot/private/credentials/paypal_credentials";
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
        global $createdDiscordBot, $logger;
        load_sql_database();
        $botID = $discord->id;

        if (!empty($discord->guilds->toArray())) {
            foreach ($discord->guilds as $guild) {
                if ($guild instanceof Guild) {
                    $member = $guild->members->toArray()[$botID];

                    if ($member instanceof Member) {
                        if (empty($member->roles->toArray())) {
                            //$guild->leave();
                            $logger->logError(null, "Bot ignored guild " . $guild->id . " because it has no roles and therefore no permissions.");
                        } else {
                            $valid = false;

                            foreach ($member->roles as $role) {
                                if ($role->permissions->administrator
                                    || $role->permissions->manage_channels
                                    && $role->permissions->create_instant_invite
                                    && $role->permissions->view_channel

                                    && $role->permissions->send_messages
                                    && $role->permissions->create_public_threads
                                    && $role->permissions->create_private_threads
                                    && $role->permissions->send_messages_in_threads
                                    && $role->permissions->manage_messages
                                    && $role->permissions->manage_threads
                                    && $role->permissions->read_message_history
                                    && $role->permissions->add_reactions

                                    && $role->permissions->mute_members
                                    && $role->permissions->move_members) {
                                    $valid = true;
                                    break;
                                }
                            }

                            if (!$valid) {
                                //$guild->leave();
                                $logger->logError(null, "Bot ignored guild " . $guild->id . " because it has no administrator permissions.");
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
                $createdDiscordBot->tranferredMessages->trackCreation($message);

                foreach ($createdDiscordBot->plans as $plan) {
                    if ($ai && $plan->aiMessages->textAssistance($message)) {
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
            $logger->logInfo($message->guild, $message->user_id, Event::MESSAGE_CREATE, $message);
        });

        $discord->on(Event::MESSAGE_DELETE, function (object $message, Discord $discord) use ($logger, $createdDiscordBot) {
            $createdDiscordBot->tranferredMessages->trackDeletion($message);

            foreach ($createdDiscordBot->plans as $plan) {
                if ($plan->countingChannels->ignoreDeletion === 0) {
                    if ($plan->countingChannels->restore($message)) {
                        break;
                    }
                } else {
                    $plan->countingChannels->ignoreDeletion--;
                }
                $plan->objectiveChannels->trackDeletion($message);
            }
            $logger->logInfo($message?->guild_id, null, Event::MESSAGE_DELETE, $message);
        });

        $discord->on(Event::MESSAGE_UPDATE, function (Message $message, Discord $discord) use ($logger, $createdDiscordBot) {
            $createdDiscordBot->tranferredMessages->trackModification($message);

            foreach ($createdDiscordBot->plans as $plan) {
                if ($plan->countingChannels->ignoreDeletion === 0) {
                    if ($plan->countingChannels->moderate($message)) {
                        break;
                    }
                } else {
                    $plan->countingChannels->ignoreDeletion--;
                }
                $plan->objectiveChannels->trackModification($message);
            }
            $logger->logInfo($message->guild, $message->user_id, Event::MESSAGE_UPDATE, $message);
        });

        // Event::MESSAGE_DELETE_BULK: Results in error

        // Separator

        $discord->on(Event::APPLICATION_COMMAND_PERMISSIONS_UPDATE, function (CommandPermissions $commandPermission, Discord $discord, ?CommandPermissions $oldCommandPermission) use ($logger) {
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

            foreach ($createdDiscordBot->plans as $plan) {
                if ($plan->userTickets->ignoreDeletion === 0) {
                    $plan->userTickets->closeByChannel($channel, null, null, false);
                } else {
                    $plan->userTickets->ignoreDeletion--;
                }
                if ($plan->userTargets->ignoreChannelDeletion === 0) {
                    $plan->userTargets->closeByChannelOrThread($channel, null, null, false);
                } else {
                    $plan->userTargets->ignoreChannelDeletion--;
                }
                if ($plan->userQuestionnaire->ignoreChannelDeletion === 0) {
                    $plan->userQuestionnaire->closeByChannelOrThread($channel, null, null, null, false);
                } else {
                    $plan->userQuestionnaire->ignoreChannelDeletion--;
                }
                if ($plan->temporaryChannels->ignoreDeletion === 0) {
                    $plan->temporaryChannels->closeByChannel($channel, false);
                } else {
                    $plan->temporaryChannels->ignoreDeletion--;
                }
            }
            $logger->logInfo($channel->guild, null, Event::CHANNEL_DELETE, $channel);
        });

        $discord->on(Event::CHANNEL_PINS_UPDATE, function ($pins, Discord $discord) use ($logger) {
            $logger->logInfo($pins?->guild, null, Event::CHANNEL_PINS_UPDATE, $pins);
        });

        // Separator

        $discord->on(Event::THREAD_CREATE, function (Thread $thread, Discord $discord) use ($logger, $createdDiscordBot) {
            $createdDiscordBot->statisticsChannels->refresh();

            foreach ($createdDiscordBot->plans as $plan) {
                if ($plan->messageNotifications->executeThread($thread)) {
                    break;
                }
            }
            $logger->logInfo($thread->guild, $thread->owner_id, Event::THREAD_CREATE, $thread);
        });

        $discord->on(Event::THREAD_UPDATE, function (Thread $thread, Discord $discord, ?Thread $oldThread) use ($logger) {
            $logger->logInfo($thread->guild, null, Event::THREAD_UPDATE, $thread, $oldThread);
        });

        $discord->on(Event::THREAD_DELETE, function (object $thread, Discord $discord) use ($logger, $createdDiscordBot) {
            $createdDiscordBot->statisticsChannels->refresh();

            if ($thread instanceof Thread) {
                foreach ($createdDiscordBot->plans as $plan) {
                    if ($plan->userTargets->ignoreThreadDeletion === 0) {
                        $plan->userTargets->closeByChannelOrThread($thread->parent);
                    } else {
                        $plan->userTargets->ignoreThreadDeletion--;
                    }
                    if ($plan->userQuestionnaire->ignoreThreadDeletion === 0) {
                        $plan->userQuestionnaire->closeByChannelOrThread($thread->parent);
                    } else {
                        $plan->userQuestionnaire->ignoreThreadDeletion--;
                    }
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
                foreach ($createdDiscordBot->plans as $plan) {
                    $plan->statusMessages->run($member, DiscordStatusMessages::GOODBYE);
                }
                $logger->logInfo($member->guild, $member->id, Event::GUILD_MEMBER_REMOVE, $member);
            } else {
                $logger->logInfo($member?->guild, null, Event::GUILD_MEMBER_REMOVE, $member);
            }
        });

        $discord->on(Event::GUILD_MEMBER_ADD, function (Member $member, Discord $discord) use ($logger, $createdDiscordBot) {
            $createdDiscordBot->joinRoles->run($member);
            $createdDiscordBot->statisticsChannels->refresh();

            foreach ($createdDiscordBot->plans as $plan) {
                $plan->statusMessages->run($member, DiscordStatusMessages::WELCOME);
            }
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
            DiscordInviteTracker::track($invite->guild);
            $logger->logInfo($invite->guild, $invite->inviter->id, Event::INVITE_CREATE, $invite);
        });

        $discord->on(Event::INVITE_DELETE, function (object $invite, Discord $discord) use ($logger, $createdDiscordBot) {
            $createdDiscordBot->statisticsChannels->refresh();
            DiscordInviteTracker::track($invite->guild);

            if ($invite instanceof Invite) {
                $logger->logInfo($invite->guild, $invite->inviter?->id, Event::INVITE_DELETE, $invite);
            } else {
                $logger->logInfo(null, null, Event::INVITE_DELETE, $invite);
            }
        });

        // Separator

        $discord->on(Event::INTERACTION_CREATE, function (Interaction $interaction, Discord $discord) use ($logger) {
            $logger->logInfo($interaction->guild, $interaction->user->id, Event::INTERACTION_CREATE, $interaction, null, false);
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
                $plan->component->handleReaction($reaction);
            }
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
                foreach ($createdDiscordBot->plans as $plan) {
                    if ($plan->temporaryChannels->trackLeave($oldstate)) {
                        break;
                    }
                }
            } else {
                $mute = $createdDiscordBot->mute->isMuted($state->member, $state->channel, DiscordMute::VOICE);

                if ($mute !== null) {
                    $state->channel->muteMember($state->member, $mute->creation_reason);
                    $createdDiscordBot->utilities->sendMessageInPieces($state->member, $mute->creation_reason);
                } else if ($createdDiscordBot->mute->wasMuted($state->member, $state->channel, DiscordMute::VOICE)) {
                    $state->channel->unmuteMember($state->member);
                }
                if ($oldstate === null) {
                    foreach ($createdDiscordBot->plans as $plan) {
                        if ($plan->temporaryChannels->trackJoin($state)) {
                            break;
                        }
                    }
                } else if ($state->channel_id != $oldstate->channel_id) {
                    foreach ($createdDiscordBot->plans as $plan) {
                        if ($plan->temporaryChannels->trackLeave($oldstate)) {
                            break;
                        }
                    }
                    foreach ($createdDiscordBot->plans as $plan) {
                        if ($plan->temporaryChannels->trackJoin($state)) {
                            break;
                        }
                    }
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
}

initiate_discord_bot();
