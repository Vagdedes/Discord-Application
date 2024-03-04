<?php

class DiscordWebAttachments
{
    private DiscordBot $bot;

    public function __construct(DiscordBot $bot)
    {
        $this->bot = $bot;
    }

    // create-web-attachment, delete-web-attachment, update-web-attachment, list-web-attachment
}