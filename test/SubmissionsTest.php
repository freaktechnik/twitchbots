<?php

use \Mini\Model\{PingablePDO, Submissions};

class SubmissionsTest extends PHPUnit_Extensions_Database_TestCase
{
    // Database connection efficieny
    static private $pdo = null;
    private $conn = null;

    /**
     * @var \Mini\Model\Submissions
     */
    private $submissions;

    /**
     * @var int
     */
    const pageSize = 100;

    public function __construct()
    {
        // We need this so sessions work
        ob_start();

        $this->getConnection();
        $pdo = self::$pdo;
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
            date timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            channel varchar(535) CHARACTER SET ascii DEFAULT NULL,
            PRIMARY KEY (name),
            FOREIGN KEY (type) REFERENCES types(id)
        ) DEFAULT CHARSET=ascii');
        $pdo->query('CREATE TABLE IF NOT EXISTS config (
            name varchar(120) CHARACTER SET ascii NOT NULL,
            value varchar(100) CHARACTER SET ascii DEFAULT NULL,
            PRIMARY KEY (name)
        ) DEFAULT CHARSET=ascii');
        $pdo->query('CREATE TABLE IF NOT EXISTS check_tokens (
            id int(10) unsigned NOT NULL AUTO_INCREMENT,
            token varchar(535) CHARACTER SET ascii NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY(token)
        ) DEFAULT CHARSET=ascii AUTO_INCREMENT=2');
        $pdo->query('CREATE OR REPLACE VIEW count AS SELECT count(name) AS count FROM bots');
        $pdo->query('CREATE OR REPLACE VIEW list AS SELECT bots.name AS name, type, multichannel, types.name AS typename FROM bots LEFT JOIN types ON bots.type = types.id ORDER BY name ASC');
        $pdo->query('CREATE OR REPLACE VIEW typelist AS SELECT id, types.name AS name, multichannel, COUNT(DISTINCT(bots.name)) AS count FROM types LEFT JOIN bots ON bots.type = types.id GROUP BY id ORDER BY name ASC');

        parent::__construct();
    }

    /**
     * @return PHPUnit_Extensions_Database_DB_IDatabaseConnection
     */
    public function getConnection()
    {
        if ($this->conn === null) {
            if (self::$pdo == null) {
                self::$pdo = new PingablePDO('mysql:dbname='.$GLOBALS['DB_NAME'].';host='.$GLOBALS['DB_HOST'].';port='.$GLOBALS['DB_PORT'], $GLOBALS['DB_USER'], $GLOBALS['DB_PASSWD']);
            }
            $this->conn = $this->createDefaultDBConnection(self::$pdo->getOriginalPDO(), ':memory:');
        }

        return $this->conn;
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
        $this->submissions = new Submissions(self::$pdo, self::pageSize);
        parent::setUp();
    }

    public function testGetSubmissions()
    {
        $this->assertEquals(count($this->submissions->getSubmissions()), $this->getConnection()->getRowCount('submissions'), "Not an empty array with no submissions");

        $this->model->append("test", "lorem ipsum", Submissions::SUBMISSION);
        $this->model->append("nightboot", "1", Submissions::CORRECTION);
        $this->assertEquals(2, $this->getConnection()->getRowCount('submissions'), "Test setup failed");

        $submissions = $this->submissions->getSubmissions();

        $this->assertCount($this->getConnection()->getRowCount('submissions'), $submissions);

        foreach($submissions as $submission) {
            $this->assertObjectHasAttribute("name", $submission);
            $this->assertObjectHasAttribute("description", $submission);
            $this->assertObjectHasAttribute("date", $submission);
            $this->assertGreaterThanOrEqual(strtotime($submission->date), time());
            $this->assertObjectHasAttribute("id", $submission);
            $this->assertObjectHasAttribute("offline", $submission);
            $this->assertObjectHasAttribute("online", $submission);
            $this->assertObjectHasAttribute("ismod", $submission);
        }

        // Sort order is descending by timestamp
        //TODO test array?
    }

    public function testGetLastSubmissionsUpdate()
    {
        $this->submissions->append("test", "1", Submissions::CORRECTION);

        $submissions = $this->submissions->getSubmissions();
        $lastModified = $this->submissions->getLastUpdate();

        $this->assertEquals($lastModified, strtotime($submissions[0]->date));
    }
}
