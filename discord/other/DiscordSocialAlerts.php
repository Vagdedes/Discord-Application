<?php

class DiscordSocialAlerts
{
    private DiscordPlan $plan;

    public function __construct(DiscordPlan $plan)
    {
        $this->plan = $plan;
    }

    // todo instagram post, twitter tweet, reddit post, facebook post, pinterest pin, tumblr post,
    // todo youtube video, tiktok video, twitch stream
    // todo spotify track, soundcloud track
}