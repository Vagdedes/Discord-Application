<?php

class DiscordStatisticChannels
{
    private DiscordPlan $plan;

    private const
        ONLINE_BOTS = "online_bots",
        ONLINE_HUMANS = "online_humans",
        ONLINE_MEMBERS = "online_members",
        OFFLINE_BOTS = "offline_bots",
        OFFLINE_HUMANS = "offline_humans",
        OFFLINE_MEMBERS = "offline_members",
        ALL_BOTS = "all_bots",
        ALL_HUMANS = "all_humans",
        ALL_MEMBERS = "all_members",
        TEXT_CHANNELS = "text_channels",
        VOICE_CHANNELS = "voice_channels",
        ROLES = "roles";

    public function __construct(DiscordPlan $plan)
    {
        $this->plan = $plan;
    }

    //todo
}