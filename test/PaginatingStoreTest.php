<?php

include_once('_fixtures/setup.php');

use \Mini\Model\PingablePDO;
use PHPUnit\Framework\TestCase;
use PHPUnit\DbUnit\TestCaseTrait;

/**
 * @coversDefaultClass \Mini\Model\PaginatingStore
 */
class PaginatingStoreTest extends TestCase
{
    use TestCaseTrait;

    // Database connection efficieny
    static private $pdo = null;
    private $conn = null;

    /**
     * @var \Mini\Model\PaginatingStore
     */
    private $store;

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
        return $this->createXMLDataSet(dirname(__FILE__).'/_fixtures/bots.xml');
    }

    public function setUp()
    {
        $this->store = new \Mini\Model\PaginatingStore(self::$pdo, "config", self::pageSize);
        parent::setUp();
    }

    /**
     * @covers ::getPageCount
     */
    public function testGetPageCount()
    {
        for($count = 0; $count < self::pageSize * 4; ++$count) {
            $expectedPageCount = ceil($count / (float)self::pageSize);
            $pageCount = $this->store->getPageCount(self::pageSize, $count);

            $this->assertEquals($expectedPageCount, $pageCount);
        }
    }

    /**
     * @covers ::getOffset
     */
    public function testGetOffset()
    {
        for($page = 1; $page < 4; ++$page) {
            $expectedOffset = ($page - 1) * self::pageSize;
            $offset = $this->store->getOffset($page);

            $this->assertEquals($expectedOffset, $offset);
        }
    }
}
