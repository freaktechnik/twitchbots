<?php

use \Mini\Model\PingablePDO;
use PHPUnit\Framework\TestCase;
use PHPUnit\DbUnit\TestCaseTrait;

/**
 * @coversDefaultClass \Mini\Model\Types
 */
class TypesTest extends TestCase
{
    use TestCaseTrait;

    // Database connection efficieny
    static private $pdo = null;
    private $conn = null;

    /**
     * @var \Mini\Model\Types
     */
    private $types;

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

    public function getConnection(): PHPUnit\DbUnit\Database\DefaultConnection
    {
        if ($this->conn === null) {
            if (self::$pdo == null) {
                self::$pdo = create_pdo($GLOBALS);
            }
            $this->conn = $this->createDefaultDBConnection(self::$pdo->getOriginalPDO(), ':memory:');
        }

        return $this->conn;
    }

    public function getDataSet(): PHPUnit_Extensions_Database_DataSet_IDataSet
    {
        return $this->createXMLDataSet(dirname(__FILE__).'/_fixtures/bots.xml');
    }

    public function setUp()
    {
        $this->types = new \Mini\Model\Types(self::$pdo, self::pageSize);
        parent::setUp();
    }

    /**
     * @covers ::getTypes
     */
    public function testGetTypes()
    {
        $bots = $this->types->getTypes();

        $queryTable = $this->getConnection()->createQueryTable(
            'bots', 'SELECT name FROM types LIMIT '.self::pageSize
        );

        $this->assertCount($queryTable->getRowCount(), $bots);

        foreach($bots as $bot) {
            $this->assertObjectHasAttribute("name", $bot);
            $this->assertObjectHasAttribute("id", $bot);
            $this->assertObjectHasAttribute("multichannel", $bot);
            $this->assertObjectHasAttribute("count", $bot);
        }

        $bots = $this->types->getTypes(2);
        $queryTable = $this->getConnection()->createQueryTable(
            'bots', 'SELECT name FROM types LIMIT '.self::pageSize.','.self::pageSize
        );
        $this->assertCount($queryTable->getRowCount(), $bots);
    }

    /**
     * @covers ::getType
     */
    public function testGetType()
    {
        $type = $this->types->getType(1);

        $this->assertEquals("Nightbot", $type->name);
        $this->assertEquals(1, $type->id);
        $this->assertEquals(true, $type->multichannel);
        $this->assertEquals("https://www.nightbot.tv/", $type->url);
    }

    /**
     * @covers ::getType
     */
    public function testGetNotExistingType()
    {
        $type = $this->types->getType(0);

        $this->assertFalse($type);
    }

    /**
     * @covers ::getAllTypes
     */
    public function testGetAllTypes()
    {
        $types = $this->types->getAllTypes();

        $this->assertCount($this->getConnection()->getRowCount('types'), $types);

        foreach($types as $type) {
            $this->assertObjectHasAttribute("name", $type);
            $this->assertObjectHasAttribute("id", $type);
            $this->assertObjectHasAttribute("url", $type);
            $this->assertObjectHasAttribute("multichannel", $type);
            $this->assertObjectHasAttribute("date", $type);
            $this->assertGreaterThanOrEqual(strtotime($type->date), time());
        }
    }
}
