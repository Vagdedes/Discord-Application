<?php

use Discord\Parts\Channel\Channel;
use Discord\Parts\Guild\Guild;

class DiscordStatisticsChannels
{
    private DiscordBot $bot;
    private array $channels;
    private string $futureDate;

    private const
        PERMISSIONS = 18003077201,

        ONLINE_BOTS = "online_bots",
        ONLINE_HUMANS = "online_humans",
        ONLINE_MEMBERS = "online_members",
        OFFLINE_BOTS = "offline_bots",
        OFFLINE_HUMANS = "offline_humans",
        OFFLINE_MEMBERS = "offline_members",
        ALL_BOTS = "all_bots",
        ALL_HUMANS = "all_humans",
        ALL_MEMBERS = "all_members",
        ALL_CHANNELS = "all_channels",
        TEXT_CHANNELS = "text_channels",
        VOICE_CHANNELS = "voice_channels",
        ROLES = "roles",
        THREADS = "threads",
        INVITE_LINKS = "invite_links",
        INVITED_USERS = "invited_users";

    public function __construct(DiscordBot $bot)
    {
        $this->bot = $bot;
        $this->futureDate = get_past_date("1 second");
        $this->refresh();
    }

    public function refresh(): void
    {
        if (get_current_date() > $this->futureDate) {
            $this->futureDate = get_future_date("30 seconds");
            $this->channels = get_sql_query(
                BotDatabaseTable::BOT_STATISTICS_CHANNELS,
                null,
                array_merge(array(
                    array("deletion_date", null),
                    null,
                    array("expiration_date", "IS", null, 0),
                    array("expiration_date", ">", get_current_date()),
                    null
                ), $this->bot->utilities->getServersQuery())
            );

            if (!empty($this->channels)) {
                $this->process(0);
            }
        }
    }

    private function process(int $position): void
    {
        $row = $this->channels[$position] ?? null;

        if ($row !== null) {
            $guild = $this->bot->utilities->getGuild($row->server_id);

            if ($guild === null) {
                global $logger;
                $logger->logError(
                    "Failed to find guild with ID '" . $row->server_id . "' for statistics channel with ID '" . $row->id
                );
                return;
            }
            if ($row->channel_id === null) {
                $name = $this->bot->listener->callChannelStatisticsImplementation(
                    $row->listener_class,
                    $row->listener_method,
                    $guild,
                    null,
                    $this->getName($guild, $row),
                    $row
                );
                $this->bot->utilities->createChannel(
                    $guild,
                    Channel::TYPE_VOICE,
                    $row->category_id,
                    $name,
                    null,
                    array(
                        array(
                            "allow" => 0,
                            "deny" => self::PERMISSIONS
                        )
                    )
                )?->done(function (Channel $channel) use ($position, $row, $guild) {
                    if (set_sql_query(
                        BotDatabaseTable::BOT_STATISTICS_CHANNELS,
                        array(
                            "channel_id" => $channel->id
                        ),
                        array(
                            array("id", $row->id)
                        ),
                        null,
                        1
                    )) {
                        $this->process($position + 1);
                    }
                });
            } else {
                $channel = $this->bot->discord->getChannel($row->channel_id);

                if ($channel !== null
                    && $channel->allowVoice()
                    && $channel->guild_id === $row->server_id
                    && $channel->parent_id === $row->category_id) {
                    $channel->name = $this->bot->listener->callChannelStatisticsImplementation(
                        $row->listener_class,
                        $row->listener_method,
                        $guild,
                        $channel,
                        $this->getName($guild, $row),
                        $row
                    );
                    $guild->channels->save($channel)->done(function () use ($position) {
                        $this->process($position + 1);
                    });
                } else {
                    global $logger;
                    $logger->logError(
                        "Failed to find statistics channel with ID '{$row->channel_id}' in guild with ID:" . $row->server_id
                    );
                }
            }
        }
    }

    private function getName(Guild $guild, object $row): ?string
    {
        $placeholder = $row->placeholder;

        switch ($row->type) {
            case self::ONLINE_BOTS:
                if ($placeholder === null) {
                    $placeholder = "Online Bots: ";
                }
                $statistic = 0;

                foreach ($guild->members as $member) {
                    if ($member->user->bot && $member->status !== "offline") {
                        $statistic++;
                    }
                }
                break;
            case self::ONLINE_HUMANS:
                if ($placeholder === null) {
                    $placeholder = "Online Humans: ";
                }
                $statistic = 0;

                foreach ($guild->members as $member) {
                    if (!$member->user->bot && $member->status !== "offline") {
                        $statistic++;
                    }
                }
                break;
            case self::ONLINE_MEMBERS:
                if ($placeholder === null) {
                    $placeholder = "Online Members: ";
                }
                $statistic = 0;

                foreach ($guild->members as $member) {
                    if ($member->status !== "offline") {
                        $statistic++;
                    }
                }
                break;
            case self::OFFLINE_BOTS:
                if ($placeholder === null) {
                    $placeholder = "Offline Bots: ";
                }
                $statistic = 0;

                foreach ($guild->members as $member) {
                    if ($member->user->bot && $member->status === "offline") {
                        $statistic++;
                    }
                }
                break;
            case self::OFFLINE_HUMANS:
                if ($placeholder === null) {
                    $placeholder = "Offline Humans: ";
                }
                $statistic = 0;

                foreach ($guild->members as $member) {
                    if (!$member->user->bot && $member->status === "offline") {
                        $statistic++;
                    }
                }
                break;
            case self::OFFLINE_MEMBERS:
                if ($placeholder === null) {
                    $placeholder = "Offline Members: ";
                }
                $statistic = 0;

                foreach ($guild->members as $member) {
                    if ($member->status === "offline") {
                        $statistic++;
                    }
                }
                break;
            case self::ALL_BOTS:
                if ($placeholder === null) {
                    $placeholder = "Server Bots: ";
                }
                $statistic = 0;

                foreach ($guild->members as $member) {
                    if ($member->user->bot) {
                        $statistic++;
                    }
                }
                break;
            case self::ALL_HUMANS:
                if ($placeholder === null) {
                    $placeholder = "Server Humans: ";
                }
                $statistic = 0;

                foreach ($guild->members as $member) {
                    if (!$member->user->bot) {
                        $statistic++;
                    }
                }
                break;
            case self::ALL_MEMBERS:
                if ($placeholder === null) {
                    $placeholder = "Server Members: ";
                }
                $statistic = sizeof($guild->members->toArray());
                break;
            case self::TEXT_CHANNELS:
                if ($placeholder === null) {
                    $placeholder = "Text Channels: ";
                }
                $statistic = 0;

                foreach ($guild->channels as $channel) {
                    if ($channel->allowText()) {
                        $statistic++;
                    }
                }
                break;
            case self::VOICE_CHANNELS:
                if ($placeholder === null) {
                    $placeholder = "Voice Channels: ";
                }
                $statistic = 0;

                foreach ($guild->channels as $channel) {
                    if ($channel->allowVoice()) {
                        $statistic++;
                    }
                }
                break;
            case self::ROLES:
                if ($placeholder === null) {
                    $placeholder = "Server Roles: ";
                }
                $statistic = sizeof($guild->roles->toArray());
                break;
            case self::ALL_CHANNELS:
                if ($placeholder === null) {
                    $placeholder = "Server Channels: ";
                }
                $statistic = sizeof($guild->channels->toArray());
                break;
            case self::INVITE_LINKS:
                if ($placeholder === null) {
                    $placeholder = "Invite Links: ";
                }
                $statistic = DiscordInviteTracker::getInviteLinks();
                break;
            case self::INVITED_USERS:
                if ($placeholder === null) {
                    $placeholder = "Invited Users: ";
                }
                $statistic = DiscordInviteTracker::getInvitedUsers();
                break;
            case self::THREADS:
                if ($placeholder === null) {
                    $placeholder = "Server Threads: ";
                }
                $statistic = 0;

                foreach ($guild->channels as $channel) {
                    $statistic += sizeof($channel->threads->toArray());
                }
                break;
            default:
                $statistic = null;
                break;
        }
        if ($statistic !== null) {
            return $placeholder . number_format($statistic);
        } else {
            return null;
        }
    }
}