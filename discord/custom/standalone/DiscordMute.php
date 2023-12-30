<?php

class DiscordMute
{
    private DiscordBot $bot;
    private array $voice, $text, $both;

    public const
        VOICE = "voice",
        TEXT = "text",
        BOTH = "both";

    public function __construct(DiscordBot $bot)
    {
        $this->bot = $bot;
    }

    //todo voice & chat mute but separate
    //todo make it channel specific
}