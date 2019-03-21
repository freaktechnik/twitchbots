<?php

namespace Mini\Model;

/* CREATE TABLE IF NOT EXISTS config (
    name varchar(120) CHARACTER SET ascii NOT NULL,
    value varchar(100) CHARACTER SET ascii DEFAULT NULL,
    PRIMARY KEY (name)
) DEFAULT CHARSET=ascii */

use PDO;

class Config extends Store {
    private static $schema = [
        "3v-ua" => "string",
        "client-ID" => "string",
        "auth0_clientId" => "string",
        "auth0_clientSecret" => "password",
        "auth0_domain" => "string",
        "auth0_redirectUrl" => "string",
        "checks_per_day" => "number"
    ];

    private static $labels = [
        "3v-ua" => "3v.fi Mod Lookup API User-Agent",
        "client-ID" => "Twitch Client ID",
        "auth0_clientId" => "Auth0 Client ID",
        "auth0_clientSecret" => "Auth0 Client Secret",
        "auth0_comain" => "Auth0 Domain",
        "auth0_redirectUrl" => "Auth0 redirect URL",
        "checks_per_day" => "Scheduled crawls per day"
    ];

    function __construct(PingablePDO $db)
    {
        parent::__construct($db, "config");
    }

    public function get(string $key, $default = ""): string
    {
        $query = $this->prepareSelect("value", "WHERE name=?");
        $query->execute([ $key ]);
        $query->setFetchMode(PDO::FETCH_CLASS, ConfigItem::class);
        /** @var ConfigItem $result */
        $result = $query->fetch();
        if($result && isset($result->value)) {
            return $result->value;
        }
        return $default;
    }

    public function set(string $key, string $value): void
    {
        $query = $this->prepareUpdate("value=? WHERE name=?");
        $query->execute([ $value, $key ]);
    }
}
