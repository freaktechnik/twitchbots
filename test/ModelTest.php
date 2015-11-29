<?php

class ModelTest extends PHPUnit_Extensions_Database_TestCase
{
    /**
     * @var \Mini\Model\Model
     */
    private $model;

    public function __construct()
    {
        // We need this so sessions work
        ob_start();

        $pdo = new PDO('mysql:dbname='.$GLOBALS['DB_NAME'].';host='.$GLOBALS['DB_HOST'].';port='.$GLOBALS['DB_PORT'], $GLOBALS['DB_USER'], $GLOBALS['DB_PASSWD']);
        $pdo->query('CREATE DATABASE IF NOT EXISTS '.$GLOBALS['DB_NAME'].' DEFAULT CHARACTER SET latin1');
        $pdo->query('CREATE TABLE IF NOT EXISTS bots (
            name varchar(535) CHARACTER SET ascii NOT NULL,
            type int(10) unsgined NOT NULL,
            date timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (name)
        ) ENDINGE=InnoDB DEFAULT CHARSET=latin1');
        $pdo->query('CREATE TABLE IF NOT EXISTS submissions (
            id int(10) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(535) CHARACTER SET ascii NOT NULL,
            description text NOT NULL,
            date timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=9');
        $pdo->query('CREATE TABLE IF NOT EXISTS types (
            id int(10) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(535) CHARACTER SET ascii NOT NULL,
            multichannel tinyint(1) NOT NULL,
            url text CHARACTER SET ascii NOT NULL,
            date timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=37');
        $pdo->query('CREATE OR REPLACE VIEW count AS SELECT count(name) AS count FROM bots');
        $pdo->query('CREATE OR REPLACE VIEW list AS SELECT name, multichannel, url, types.name AS typename FROM bots LEFT JOIN types ON bots.type = types.id');
    }

    /**
     * @return PHPUnit_Extensions_Database_DB_IDatabaseConnection
     */
    public function getConnection()
    {
        $pdo = new PDO('mysql:dbname='.$GLOBALS['DB_NAME'].';host='.$GLOBALS['DB_HOST'].';port='.$GLOBALS['DB_PORT'], $GLOBALS['DB_USER'], $GLOBALS['DB_PASSWD']);
        return $this->createDefaultDBConnection($pdo, $GLOBALS['DB_NAME']);
    }

    /**
     * @return PHPUnit_Extensions_Database_DataSet_IDataSet
     */
    public function getDataSet()
    {
        return $this->createXMLDataSet(dirname(__FILE__).'/_fixtures/bots.xml');
    }

    public function setUp()
    {
        $this->model = new \Mini\Model\Model(array(
            'db_host' => $GLOBALS['DB_HOST'],
            'db_port' => $GLOBALS['DB_PORT'],
            'db_name' => $GLOBALS['DB_NAME'],
            'db_user' => $GLOBALS['DB_USER'],
            'db_pass' => $GLOBALS['DB_PASSWD'],
            'page_size' => 100
        ));
    }

    public function testCSRFTokenValidation()
    {
        $formname = "test";
        $token = $this->model->getToken($formname);
        $this->assertStringMatchesFormat('%s', $token);
        $this->assertTrue($this->model->checkToken($formname, $token));
    }
}
?>
