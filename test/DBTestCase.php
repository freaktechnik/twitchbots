<?php

use \Mini\Model\PingablePDO;
use PHPUnit\Framework\TestCase;
use PHPUnit\DbUnit\TestCaseTrait;

class DBTestCase extends TestCase {
    use TestCaseTrait;

    const TABLES = [
        "config" => [
            'name varchar(120) CHARACTER SET ascii NOT NULL,
             value varchar(100) CHARACTER SET ascii DEFAULT NULL,
             PRIMARY KEY (name)',
             ''
        ],
        "submissions" => [
            'id int(10) unsigned NOT NULL AUTO_INCREMENT,
            twitch_id varchar(20) CHARACTER SET ascii NOT NULL,
            name varchar(535) CHARACTER SET utf8 NOT NULL,
            description text NOT NULL,
            date timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            type int(1) unsigned NOT NULL DEFAULT 0,
            channel varchar(535) CHARACTER SET utf8 DEFAULT NULL,
            channel_id varchar(20) CHARACTER SET ascii DEFAULT NULL,
            offline boolean DEFAULT NULL,
            online boolean DEFAULT NULL,
            ismod boolean DEFAULT NULL,
            following int(10) unsigned DEFAULT NULL,
            following_channel boolean DEFAULT NULL,
            bio text DEFAULT NULL,
            vods boolean DEFAULT NULL,
            verified boolean DEFAULT NULL,
            PRIMARY KEY (id)',
            'AUTO_INCREMENT=9'
        ],
        "types" => [
            'id int(10) unsigned NOT NULL AUTO_INCREMENT,
              name varchar(255) CHARACTER SET utf8 NOT NULL,
              multichannel tinyint(1) NOT NULL,
              url text CHARACTER SET ascii NOT NULL,
              date timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY name (name)',
              'AUTO_INCREMENT=37'
        ],
        "bots" => [
            'twitch_id varchar(20) CHARACTER SET ascii DEFAULT NULL,
              name varchar(255) CHARACTER SET utf8 NOT NULL,
              type int(10) unsigned DEFAULT NULL,
              cdate timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              date timestamp NOT NULL DEFAULT 0,
              channel varchar(535) CHARACTER SET utf8 DEFAULT NULL,
              channel_id varchar(20) CHARACTER SET ascii DEFAULT NULL,
              PRIMARY KEY (name),
              FOREIGN KEY (type) REFERENCES types(id),
              UNIQUE KEY twitch_id (twitch_id)',
              ''
        ],
        "authorized_users" => [
            'id int(10) unsigned NOT NULL AUTO_INCREMENT,
            email MEDIUMTEXT CHARACTER SET ascii NOT NULL,
            PRIMARY KEY (id)',
            'AUTO_INCREMENT=2'
        ]
    ];

    // Database connection efficieny
    static protected $pdo = null;
    static protected $dataSet = 'bots';
    static protected $tables = [
        'types',
        'bots',
        'submissions',
        'config',
        'authorized_users'
    ];
    private $conn = null;

    private static function createTable($table)
    {
        $tableInfo = self::TABLES[$table];
        static::$pdo->query('CREATE TABLE IF NOT EXISTS '.$table.' ('.$tableInfo[0].') DEFAULT CHARSET=utf8 '.$tableInfo[1]);
    }

    public static function setUpBeforeClass()
    {
        $options = array(PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ, PDO::ATTR_ERRMODE => PDO::ERRMODE_WARNING);
        static::$pdo = new PingablePDO('mysql:dbname='.$GLOBALS['DB_NAME'].';host='.$GLOBALS['DB_HOST'].';port='.$GLOBALS['DB_PORT'], $GLOBALS['DB_USER'], $GLOBALS['DB_PASSWD'], $options);
        foreach(static::$tables as $t) {
            self::createTable($t);
        }

        if(in_array('bots', static::$tables)) {
            static::$pdo->query('CREATE OR REPLACE VIEW count AS SELECT count(name) AS count FROM bots');
            if(in_array('types', static::$tables)) {
                static::$pdo->query('CREATE OR REPLACE VIEW list AS SELECT bots.name AS name, type, multichannel, types.name AS typename FROM bots LEFT JOIN types ON bots.type = types.id ORDER BY name ASC');
                static::$pdo->query('CREATE OR REPLACE VIEW typelist AS SELECT id, types.name AS name, multichannel, COUNT(DISTINCT(bots.name)) AS count FROM types LEFT JOIN bots ON bots.type = types.id GROUP BY id ORDER BY name ASC');
            }
        }

        parent::setUpBeforeClass();
    }

    public static function tearDownAfterClass()
    {
        static::$pdo = null;

        parent::tearDownAfterClass();
    }

    public function getConnection(): PHPUnit\DbUnit\Database\DefaultConnection
    {
        if ($this->conn === null) {
            $this->conn = $this->createDefaultDBConnection(static::$pdo->getOriginalPDO(), ':memory:');
        }

        return $this->conn;
    }

    public function getDataSet(): PHPUnit\DbUnit\DataSet\XmlDataSet
    {
        return $this->createXMLDataSet(dirname(__FILE__).'/_fixtures/'.static::$dataSet.'.xml');
    }
}
