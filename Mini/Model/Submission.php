<?php

namespace Mini\Model;

class Submission extends Row {
    /** @var int $id */
    public $id;
    /** @var string $twitch_id */
    public $twitch_id;
    /** @var string $name */
    public $name;
    /** @var string $description */
    public $description;
    /** @var int $type */
    public $type;
    /** @var string|null $channel */
    public $channel = null;
    /** @var string|null channel_id */
    public $channel_id;
    /** @var bool|null $offline */
    public $offline = null;
    /** @var bool|null $online */
    public $online = null;
    /** @var bool|null $ismod */
    public $ismod = null;
    /** @var int|null $following */
    public $following = null;
    /** @var bool|null $following_channel */
    public $following_channel = null;
    /** @var string|null $bio */
    public $bio = null;
    /** @var bool|null $vods */
    public $vods = null;
    /** @var bool|null $verified */
    public $verified = null;
}
