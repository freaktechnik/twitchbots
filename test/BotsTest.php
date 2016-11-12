<?php

include_once('_fixtures/setup.php');

use \Mini\Model\PingablePDO;

class BotsTest extends PHPUnit_Extensions_Database_TestCase
{
    // Database connection efficieny
    static private $pdo = null;
    private $conn = null;

    /**
     * @var \Mini\Model\Bots
     */
    private $bots;

    /**
     * @var int
     */
    const pageSize = 100;

    public function __construct()
    {
        // We need this so sessions work
        ob_start();

        $this->getConnection();
        create_tables(self::$pdo);

        parent::__construct();
    }

    /**
     * @return PHPUnit_Extensions_Database_DB_IDatabaseConnection
     */
    public function getConnection()
    {
        if ($this->conn === null) {
            if (self::$pdo == null) {
                self::$pdo = create_pdo($GLOBALS);
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
        $this->bots = new \Mini\Model\Bots(self::$pdo, self::pageSize);
        parent::setUp();
    }

    public function testGetBot()
    {
        $bot = $this->bots->getBot("butler_of_ec0ke");

        $this->assertEquals("butler_of_ec0ke", $bot->name);
        $this->assertEquals(22, $bot->type);
        $this->assertGreaterThanOrEqual(strtotime($bot->date), time());
        $this->assertEquals("ec0ke", $bot->channel);
    }

    public function testGetNotExistingBot()
    {
        $bot = $this->bots->getBot("freaktechnik");

        $this->assertFalse($bot);
    }

    public function testGetBotsByType()
    {
        $bots = $this->bots->getBotsByType(22);

        $queryTable = $this->getConnection()->createQueryTable(
            'bots', 'SELECT name FROM bots WHERE type=22 LIMIT '.self::pageSize
        );

        $this->assertCount($queryTable->getRowCount(), $bots);

        foreach($bots as $bot) {
            $this->assertObjectHasAttribute("name", $bot);
            $this->assertObjectHasAttribute("type", $bot);
            $this->assertObjectHasAttribute("date", $bot);
            $this->assertGreaterThanOrEqual(strtotime($bot->date), time());
        }

        $bots = $this->bots->getBotsByType(22, self::pageSize);
        $queryTable = $this->getConnection()->createQueryTable(
            'bots', 'SELECT name FROM bots WHERE type=22 LIMIT '.self::pageSize.','.self::pageSize
        );
        $this->assertCount($queryTable->getRowCount(), $bots);
    }

    public function testGetCount()
    {
        $botCount = $this->bots->getCount();
        $this->assertEquals($botCount, $this->getConnection()->getRowCount('bots'));

        $botCount = $this->bots->getCount(22);
        $queryTable = $this->getConnection()->createQueryTable(
            'bots', 'SELECT name FROM bots WHERE type=22'
        );

        $this->assertEquals($botCount, $queryTable->getRowCount());
    }

    public function testGetBots()
    {
        $bots = $this->bots->getBots();

        $queryTable = $this->getConnection()->createQueryTable(
            'bots', 'SELECT name FROM bots LIMIT '.self::pageSize
        );

        $this->assertCount($queryTable->getRowCount(), $bots);

        foreach($bots as $bot) {
            $this->assertObjectHasAttribute("name", $bot);
            $this->assertObjectHasAttribute("type", $bot);
            $this->assertObjectHasAttribute("multichannel", $bot);
            $this->assertObjectHasAttribute("typename", $bot);
        }

        $bots = $this->bots->getBots(2);
        $queryTable = $this->getConnection()->createQueryTable(
            'bots', 'SELECT name FROM bots LIMIT '.self::pageSize.','.self::pageSize
        );
        $this->assertCount($queryTable->getRowCount(), $bots);
    }

    public function testGetAllRawBots()
    {
        $bots = $this->bots->getAllRawBots();

        $queryTable = $this->getConnection()->createQueryTable(
            'bots', 'SELECT name FROM bots LIMIT '.self::pageSize
        );

        $this->assertCount($queryTable->getRowCount(), $bots);

        foreach($bots as $bot) {
            $this->assertObjectHasAttribute("name", $bot);
            $this->assertObjectHasAttribute("type", $bot);
            $this->assertObjectHasAttribute("date", $bot);
            $this->assertGreaterThanOrEqual(strtotime($bot->date), time());
        }

        $bots = $this->bots->getAllRawBots(self::pageSize);
        $queryTable = $this->getConnection()->createQueryTable(
            'bots', 'SELECT name FROM bots LIMIT '.self::pageSize.','.self::pageSize
        );
        $this->assertCount($queryTable->getRowCount(), $bots);
    }

    public function testGetBotsByNames()
    {
        $names = array(
            "butler_of_ec0ke",
            "freaktechnik",
            "nightbot",
            "ec0ke",
            "syntria"
        );
        $bots = $this->bots->getBotsByNames($names);

        $this->assertCount(2, $bots);

        foreach($bots as $bot) {
            $this->assertObjectHasAttribute("name", $bot);
            $this->assertObjectHasAttribute("type", $bot);
            $this->assertObjectHasAttribute("date", $bot);
            $this->assertGreaterThanOrEqual(strtotime($bot->date), time());
        }

        $bots = $this->bots->getBotsByNames($names, self::pageSize);
        $this->assertEmpty($bots);
    }

    public function testRemoveBot()
    {
        $initialCount = $this->bots->getCount();
        $this->model->removeBot('ackbot');

        $this->assertEquals($initialCount - 1, $this->bots->getCount());

        $queryTable = $this->getConnection()->createQueryTable(
            'bots0', "SELECT * FROM bots WHERE name='ackbot'"
        );
        $this->assertEquals(0, $queryTable->getRowCount());
    }

    public function testRemoveBots()
    {
        $initialCount = $this->bots->getCount();
        $this->model->removeBots(array('ackbot', 'nightbot'));

        $this->assertEquals($initialCount - 2, $this->bots->getCount());

        $queryTable = $this->getConnection()->createQueryTable(
            'bots', "SELECT * FROM bots WHERE name IN ('ackbot','nightbot')"
        );
        $this->assertEquals(0, $queryTable->getRowCount());
    }
}
