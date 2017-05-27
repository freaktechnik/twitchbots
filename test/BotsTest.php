<?php

include_once('_fixtures/setup.php');

use \Mini\Model\PingablePDO;
use PHPUnit\Framework\TestCase;
use PHPUnit\DbUnit\TestCaseTrait;

/**
 * @coversDefaultClass \Mini\Model\Bots
 */
class BotsTest extends TestCase
{
    use TestCaseTrait;

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

    public static function setUpBeforeClass()
    {
        self::$pdo = create_pdo($GLOBALS);
        create_tables(self::$pdo);
        ob_start();
    }

    public static function tearDownAfterClass()
    {
        self::$pdo = null;
        ob_end_clean();
    }

    public function __construct()
    {
        parent::__construct();
    }

    public function getConnection(): PHPUnit\DbUnit\Database\DefaultConnection
    {
        if ($this->conn === null) {
            $this->conn = $this->createDefaultDBConnection(self::$pdo->getOriginalPDO(), ':memory:');
        }

        return $this->conn;
    }

    public function getDataSet(): PHPUnit\DbUnit\DataSet\XmlDataSet
    {
        return $this->createXMLDataSet(dirname(__FILE__).'/_fixtures/bots.xml');
    }

    public function setUp()
    {
        $this->bots = new \Mini\Model\Bots(self::$pdo, self::pageSize);
        parent::setUp();
    }

    /**
     * @covers ::getBot
     */
    public function testGetBot()
    {
        $bot = $this->bots->getBot("butler_of_ec0ke");

        $this->assertEquals("butler_of_ec0ke", $bot->name);
        $this->assertEquals(22, $bot->type);
        $this->assertGreaterThanOrEqual(strtotime($bot->date), time());
        $this->assertEquals("ec0ke", $bot->channel);
    }

    /**
     * @covers ::getBot
     */
    public function testGetNotExistingBot()
    {
        $bot = $this->bots->getBot("freaktechnik");

        $this->assertFalse($bot);
    }

    /**
     * @covers ::getBotsByType
     */
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

    /**
     * @covers ::getCount
     */
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

    /**
     * @covers ::getBots
     */
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

    /**
     * @covers ::getAllRawBots
     */
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

    /**
     * @covers ::getBotsByNames
     */
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
            $this->assertObjectNotHasAttribute("index", $bot);
            $this->assertObjectNotHasAttribute("value", $bot);
            $this->assertGreaterThanOrEqual(strtotime($bot->date), time());
        }

        $bots = $this->bots->getBotsByNames($names, self::pageSize);
        $this->assertEmpty($bots);
    }

    /**
     * @covers ::removeBot
     */
    public function testRemoveBot()
    {
        $initialCount = $this->bots->getCount();
        $this->bots->removeBot('ackbot');

        $this->assertEquals($initialCount - 1, $this->bots->getCount());

        $queryTable = $this->getConnection()->createQueryTable(
            'bots0', "SELECT * FROM bots WHERE name='ackbot'"
        );
        $this->assertEquals(0, $queryTable->getRowCount());
    }

    /**
     * @covers ::removeBots
     */
    public function testRemoveBots()
    {
        $initialCount = $this->bots->getCount();
        $this->bots->removeBots(array(1, 15));

        $this->assertEquals($initialCount - 2, $this->bots->getCount());

        $queryTable = $this->getConnection()->createQueryTable(
            'bots', "SELECT * FROM bots WHERE twitch_id IN (1,15)"
        );
        $this->assertEquals(0, $queryTable->getRowCount());
    }
}
