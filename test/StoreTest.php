<?php

include_once('_fixtures/setup.php');

use \Mini\Model\PingablePDO;

class StoreTest extends PHPUnit_Extensions_Database_TestCase
{
    // Database connection efficieny
    static private $pdo = null;
    private $conn = null;

    /**
     * @var \Mini\Model\Store
     */
    private $store;

    public function __construct()
    {
        // We need this so sessions work
        ob_start();

        $this->getConnection();
        create_config_table(self::$pdo);

        parent::__construct();
    }

    public function getConnection(): PHPUnit_Extensions_Database_DB_IDatabaseConnection
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
        return $this->createXMLDataSet(dirname(__FILE__).'/_fixtures/pdostorage.xml');
    }

    public function setUp()
    {

        $this->store = new \Mini\Model\ShimStore(self::$pdo, 'config');
        parent::setUp();
    }

    public function testPrepareInsertUpdate()
    {
        $query = $this->store->prepareInsert("(name, value) VALUES (1_new, value)");
        $this->assertInstanceOf(PDOStatement, $query);
        $query->execute();

        $query = $this->store->prepareUpdate("value=had WHERE name=1_has")->execute();
        $this->assertInstanceOf(PDOStatement, $query);
        $query->execute();

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
        $this->assertInstanceOf(PDOStatement, $query);
        $query->execute(array("1_has"));

        $result = $query->fetch();
        $this->assertEquals("yes", $result->value);
        $this->assertEquals("1_has", $result->value);
    }

    public function testPrepareDelete()
    {
        $rows = $this->getConnection()->getRowCount('config');
        $query = $this->store->prepareDelete("WHERE name=?");
        $this->assertInstanceOf(PDOStatement, $query);
        $query->execute("1_has");

        $query->fetch();

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
        $expectedTable = $this->createFlatXmlDataSet("store.xml")
                              ->getTable($table);
        $this->assertTablesEqual($expectedTable, $queryTable);

        $this->store->cleanUpTempTable($table);

        //TODO ensure table is not existing anymore
    }
}
