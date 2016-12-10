<?php

use \Mini\Model\PingablePDO;

// General test set up helpers.
function create_config_table($pdo) {
    $pdo->query('CREATE TABLE IF NOT EXISTS config (
        name varchar(120) CHARACTER SET ascii NOT NULL,
        value varchar(100) CHARACTER SET ascii DEFAULT NULL,
        PRIMARY KEY (name)
    ) DEFAULT CHARSET=ascii');
}

function create_tables($pdo) {
    $pdo->query('CREATE TABLE IF NOT EXISTS submissions (
        id int(10) unsigned NOT NULL AUTO_INCREMENT,
        name varchar(535) CHARACTER SET ascii NOT NULL,
        description text NOT NULL,
        date timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        type int(1) unsigned NOT NULL DEFAULT 0,
        channel varchar(535) CHARACTER SET ascii DEFAULT NULL,
        offline boolean DEFAULT NULL,
        online boolean DEFAULT NULL,
        ismod boolean DEFAULT NULL,
        following int(10) unsigned DEFAULT NULL,
        following_channel boolean DEFAULT NULL,
        bio text DEFAULT NULL,
        vods boolean DEFAULT NULL,
        PRIMARY KEY (id)
    ) DEFAULT CHARSET=utf8 AUTO_INCREMENT=9');
    $pdo->query('CREATE TABLE IF NOT EXISTS types (
        id int(10) unsigned NOT NULL AUTO_INCREMENT,
        name varchar(535) CHARACTER SET ascii NOT NULL,
        multichannel tinyint(1) NOT NULL,
        url text CHARACTER SET ascii NOT NULL,
        date timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY name (name)
    ) DEFAULT CHARSET=ascii AUTO_INCREMENT=37');
    $pdo->query('CREATE TABLE IF NOT EXISTS bots (
        name varchar(535) CHARACTER SET ascii NOT NULL,
        type int(10) unsigned DEFAULT NULL,
        cdate timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        date timestamp NOT NULL DEFAULT 0,
        channel varchar(535) CHARACTER SET ascii DEFAULT NULL,
        PRIMARY KEY (name),
        FOREIGN KEY (type) REFERENCES types(id)
    ) DEFAULT CHARSET=ascii');
    create_config_table($pdo);
    $pdo->query('CREATE TABLE IF NOT EXISTS authorized_users (
        id int(10) unsigned NOT NULL AUTO_INCREMENT,
        email MEDIUMTEXT CHARACTER SET ascii NOT NULL,
        PRIMARY KEY (id)
    ) DEFAULT CHARSET=ascii AUTO_INCREMENT=2');
    $pdo->query('CREATE OR REPLACE VIEW count AS SELECT count(name) AS count FROM bots');
    $pdo->query('CREATE OR REPLACE VIEW list AS SELECT bots.name AS name, type, multichannel, types.name AS typename FROM bots LEFT JOIN types ON bots.type = types.id ORDER BY name ASC');
    $pdo->query('CREATE OR REPLACE VIEW typelist AS SELECT id, types.name AS name, multichannel, COUNT(DISTINCT(bots.name)) AS count FROM types LEFT JOIN bots ON bots.type = types.id GROUP BY id ORDER BY name ASC');
}

function create_pdo(&$globals) {
    $options = array(PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ, PDO::ATTR_ERRMODE => PDO::ERRMODE_WARNING);
    return new PingablePDO('mysql:dbname='.$globals['DB_NAME'].';host='.$globals['DB_HOST'].';port='.$globals['DB_PORT'], $globals['DB_USER'], $globals['DB_PASSWD'], $options);
}
