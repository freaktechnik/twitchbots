<?php

require_once "DBTestCase.php";

use \Mini\Model\ShimStore;
/**
 * @coversDefaultClass \Mini\Model\Store
 */
class StoreTest extends DBTestCase
{
    static protected $configOnly = true;
    static protected $dataSet = 'pdostorage';

    /**
     * @var \Mini\Model\Store
     */
    private $store;

    public function setUp()
    {
        $this->store = new ShimStore(self::$pdo, 'config');

        parent::setUp();
    }

    public function tearDown()
    {
        $this->store = null;

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
