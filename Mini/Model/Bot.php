<?php

namespace Mini\Model;

class Bot extends Row {
    /**
     * Twitch user ID
     * @var string|null $twitch_id
     */
    public $twitch_id = null;
    /**
     * Twitch username of the bot
     * @var string $name
     */
    public $name;
    /**
     * Type of the bot
     * @var int|null $type
     */
    public $type = null;
    /**
     * Last crawl or update ts
     * @var string $cdate
     */
    public $cdate;
    /**
     * Channel the bot is in
     * @var string|null $channel
     */
    public $channel = null;
    /**
     * Channel Twitch ID
     * @var string|null $channel_id
     */
    public $channel_id = null;
}
