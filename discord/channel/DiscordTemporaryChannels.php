<?php

use Discord\Parts\Channel\Channel;
use Discord\Parts\User\Member;
use Discord\Parts\User\User;
use Discord\Parts\WebSockets\VoiceStateUpdate;

class DiscordTemporaryChannels
{
    private DiscordPlan $plan;
    private array $channels;

    private const
        NOT_IN_CHANNEL = "You are not in a voice channel.",
        NOT_IN_TEMPORARY_CHANNEL = "You are not in a temporary voice channel.";

    public function __construct(DiscordPlan $plan)
    {
        $this->plan = $plan;
        $this->channels = get_sql_query(
            BotDatabaseTable::BOT_TEMPORARY_CHANNELS,
            null,
            array(
                array("plan_id", $plan->planID),
                array("deletion_date", null),
                null,
                array("expiration_date", "IS", null, 0),
                array("expiration_date", ">", get_current_date()),
                null
            )
        );

        if (!empty($this->channels)) {
            foreach ($this->channels as $arrayKey => $row) {
                unset($this->channels[$arrayKey]);
                $this->channels[$row->id] = $row;
            }
        }
    }

    public function trackJoin(VoiceStateUpdate $update): bool
    {
        $state = $this->getChannel($update->channel);

        if (!empty($state)) {
            if ($state[0]) {
                //todo create
            } else if ($this->isBanned($update->channel, $update->user)) {
                //todo kick
            }
            return true;
        }
        return false;
    }

    public function trackLeave(VoiceStateUpdate $update): bool
    {
        $state = $this->getChannel($update->channel);

        if (!empty($state)) {
            if (!$state[0]) {
                $channel = $update->channel;
                $members = $channel->members->toArray();
                unset($members[$update->user->id]);

                if (empty($members)) {
                    $channel->guild->channels->delete($channel);
                } else if (!empty($this->getOwner($channel, $update->user))) {
                    foreach ($members as $member) {
                        if ($member->id != $update->user->id) {
                            $this->setOwner($member, $member->user);
                            break;
                        }
                    }
                }
            }
            return true;
        }
        return false;
    }

    // Separator

    public function setOwner(Member $member, User $user, bool $set = true, ?string $reason = null): ?string
    {
        $channel = $member->getVoiceChannel();

        if ($channel !== null) {
            $state = $this->getChannel($channel);

            if (!empty($state)) {
                if ($set) {
                    if ($this->isBanned($channel, $user)) {
                        return "User is banned from this temporary voice channel.";
                    } else if (!empty($this->isOwner($channel, $user))) {
                        return "User is already an owner of this temporary voice channel.";
                    } else {
                        foreach ($channel->members as $member) {
                            if ($member->id == $user->id) {
                                if (sql_insert(
                                    BotDatabaseTable::BOT_TEMPORARY_CHANNEL_BANS,
                                    array(
                                        "user_id" => $user->id,
                                        "temporary_channel_id" => $state[1]->id,
                                        "channel_id" => $channel->id,
                                        "creation_date" => get_current_date(),
                                        "creation_reason" => $reason,
                                        "created_by" => $member->id,
                                        "deletion_date" => get_current_date(),
                                    )
                                )) {
                                    //todo add permissions
                                    return null;
                                } else {
                                    return "Failed to insert ban into the database.";
                                }
                            }
                        }
                        return "Could not find this user in this temporary voice channel.";
                    }
                } else if ($member->id == $user->id) {
                    return "You cannot remove yourself from an owner of this temporary voice channel.";
                } else {
                    return null;
                }
            } else {
                return self::NOT_IN_TEMPORARY_CHANNEL;
            }
        } else {
            return self::NOT_IN_CHANNEL;
        }
    }

    // Separator

    public function setBan(Member $member, User $user, bool $set = true, ?string $reason = null): ?string
    {
        $channel = $member->getVoiceChannel();

        if ($channel !== null) {
            $state = $this->getChannel($channel);

            if (!empty($state)) {
                if ($set) {
                    if ($this->isBanned($channel, $user)) {
                        return "User is already banned from this temporary voice channel.";
                    } else if (sql_insert(
                        BotDatabaseTable::BOT_TEMPORARY_CHANNEL_BANS,
                        array(
                            "user_id" => $user->id,
                            "temporary_channel_id" => $state[1]->id,
                            "channel_id" => $channel->id,
                            "creation_date" => get_current_date(),
                            "creation_reason" => $reason,
                            "created_by" => $member->id,
                            "deletion_date" => get_current_date(),
                        )
                    )) {
                        $string = $this->setOwner($member, $user, false, null);

                        if ($string === null) {
                            //todo kick if found in the channel
                        }
                        return $string;
                    } else {
                        return "Failed to insert ban into the database.";
                    }
                } else {
                    if (!$this->isBanned($channel, $user)) {
                        return "User is not banned from this temporary voice channel.";
                    } else if (set_sql_query(
                        BotDatabaseTable::BOT_TEMPORARY_CHANNEL_BANS,
                        array(
                            "deletion_date" => get_current_date(),
                            "deleted_by" => $member->id,
                        ),
                        array(
                            array("deletion_date", null),
                            array("user_id", $user->id),
                            array("channel_id", $channel->id)
                        ),
                        null,
                        1
                    )) {
                        return null;
                    } else {
                        return "Failed to insert unban into the database.";
                    }
                }
            } else {
                return self::NOT_IN_TEMPORARY_CHANNEL;
            }
        } else {
            return self::NOT_IN_CHANNEL;
        }
    }

    private function isBanned(Channel $channel, User $user): bool
    {
        set_sql_cache("1 second");
        return !empty(get_sql_query(
            BotDatabaseTable::BOT_TEMPORARY_CHANNEL_BANS,
            null,
            array(
                array("deletion_date", null),
                array("user_id", $user->id),
                array("channel_id", $channel->id),
            ),
            null,
            1
        ));
    }

    private function getOwner(Channel $channel, User $user): array
    {
        set_sql_cache("1 second");
        return get_sql_query(
            BotDatabaseTable::BOT_TEMPORARY_CHANNEL_OWNERS,
            null,
            array(
                array("deletion_date", null),
                array("user_id", $user->id),
                array("channel_id", $channel->id),
            ),
            null,
            1
        );
    }

    // Separator

    private function getChannel(Channel $channel): ?array
    {
        $channelInitiator = $this->channels[$channel->id] ?? null;

        if ($channelInitiator !== null) {
            return array(true, $channelInitiator);
        } else {
            set_sql_cache("1 second");
            $query = get_sql_query(
                BotDatabaseTable::BOT_TEMPORARY_CHANNEL_TRACKING,
                null,
                array(
                    array("deletion_date", null),
                    array("channel_id", $channel->id),
                ),
                null,
                1
            );

            if (!empty($query)) {
                return array(false, $query[0]);
            }
        }
        return null;
    }
}