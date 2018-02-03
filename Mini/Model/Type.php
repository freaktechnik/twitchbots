<?php

namespace Mini\Model;

class Type extends Row {
    /** @var int $id */
    public $id;
    /** @var string $name */
    public $name;
    /** @var bool $multichannel */
    public $multichannel;
    /** @var string|null $url */
    public $url = null;
    /** @var bool $managed */
    public $managed;
    /** @var bool|null $customUsername */
    public $customUsername = null;
    /** @var string|null $identifiableby */
    public $identifiableby = null;
    /** @var string|null $description */
    public $description = null;
    /** @var bool $enabled */
    public $enabled = true;
    /** @var string|null $sourceUrl */
    public $sourceUrl = null;
    /** @var string|null $commandsUrl */
    public $commandsUrl = null;
    /** @var int|null $payment */
    public $payment = null;
    /** @var bool $hasFreeTier */
    public $hasFreeTier = true;
    /** @var int|null $apiVersion */
    public $apiVersion = null;
}
