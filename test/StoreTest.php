<?php

include_once('_fixtures/setup.php');

use \Mini\Model\PingablePDO;
use PHPUnit\Framework\TestCase;
use PHPUnit\DbUnit\TestCaseTrait;

/**
 * @coversDefaultClass \Mini\Model\Store
 */
class StoreTest extends TestCase
{
    use TestCaseTrait {
        setUp as setUpDB;
        tearDown as tearDownDB;
    };

    // Database connection efficieny
    static private $pdo = null;
    private $conn = null;

    /**
     * @var \Mini\Model\Store
     */
    private $store;

    public static function setUpBeforeClass()
    {
        self::$pdo = create_pdo($GLOBALS);
        create_config_table(self::$pdo);

        parent::setUpBeforeClass();
    }

    public static function tearDownAfterClass()
    {
        self::$pdo = null;

        parent::tearDownAfterClass();
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
        return $this->createXMLDataSet(dirname(__FILE__).'/_fixtures/pdostorage.xml');
    }

    public function setUp()
    {
        $this->store = new \Mini\Model\ShimStore(self::$pdo, 'config');

        $this->setUpDB();
        parent::setUp();
    }

    public function tearDown()
    {
        $this->store = null;

        $this->tearDownDB();
        parent::tearDown();
    }

    public function testPrepareInsertUpdate()
    {
        $query = $this->store->prepareInsert("(name, value) VALUES (?, ?)");
        $this->assertInstanceOf(PDOStatement::class, $query);
        $query->execute(array("1_new", "value"));

        $query = $this->store->prepareUpdate("value=? WHERE name=?");
        $this->assertInstanceOf(PDOStatement::class, $query);
        $query->execute(array("had", "1_has"));

        $queryTable = $this->getConnection()->createQueryTable(
            'config', 'SELECT name, value FROM config'
        );
        $expectedTable = $this->createXMLDataSet(dirname(__FILE__)."/_fixtures/config.xml")
                              ->getTable("config");
        $this->assertTablesEqual($expectedTable, $queryTable);
    }

    public function testPrepareSelect()
    {
        $query = $this->store->prepareSelect("*", "WHERE name=?");
        $this->assertInstanceOf(PDOStatement::class, $query);
        $query->execute(array("1_has"));

        $result = $query->fetch();
        $this->assertEquals("yes", $result->value);
        $this->assertEquals("1_has", $result->name);
    }

    public function testPrepareDelete()
    {
        $rows = $this->getConnection()->getRowCount('config');
        $query = $this->store->prepareDelete("WHERE name=?");
        $this->assertInstanceOf(PDOStatement::class, $query);
        $query->execute(array("1_has"));

        $this->assertEquals($rows - 1, $this->getConnection()->getRowCount('config'));
    }

    public function testGetCount()
    {
        $botCount = $this->store->getCount('config');
        $this->assertEquals($this->getConnection()->getRowCount('config'), $botCount);
    }

    public function testTempTable()
    {
        $table = $this->store->createTempTable(array(
            0 => "a",
            "a" => "b"
        ));

        $queryTable = $this->getConnection()->createQueryTable(
            $table, 'SELECT * FROM '.$table
        );
        $expectedTable = $this->createXMLDataSet(dirname(__FILE__)."/_fixtures/store.xml")
                              ->getTable($table);
        $this->assertTablesEqual($expectedTable, $queryTable);

        $this->store->cleanUpTempTable($table);

        //TODO ensure table is not existing anymore
    }
}
