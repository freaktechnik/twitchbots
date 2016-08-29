<?php

use \Mini\Model\PingablePDO;

class PDOStorageTest extends PHPUnit_Extensions_Database_TestCase
{
    // Database connection efficieny
    static private $pdo = null;
    private $conn = null;

    /**
     * @var \Mini\Model\TypeCrawler\Storage\PDOStorage
     */
    private $model;

    public function __construct()
    {
        $this->getConnection();
        $pdo = self::$pdo;

        $pdo->query('CREATE TABLE IF NOT EXISTS config (
            name varchar(120) CHARACTER SET ascii NOT NULL,
            value varchar(100) CHARACTER SET ascii DEFAULT NULL,
            PRIMARY KEY (name)
        ) DEFAULT CHARSET=ascii');

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
            $this->conn = $this->createDefaultDBConnection(self::$pdo->pdo, ':memory:');
        }

        return $this->conn;
    }

    /**
     * @return PHPUnit_Extensions_Database_DataSet_IDataSet
     */
    public function getDataSet()
    {
        return $this->createXMLDataSet(dirname(__FILE__).'/_fixtures/pdostorage.xml');
    }

    public function setUp()
    {
        $this->getConnection();
        $this->model = new \Mini\Model\TypeCrawler\Storage\PDOStorage(1, self::$pdo, 'config');
        parent::setUp();
    }

    public function testGet()
    {
        $got = $this->model->get('get');
        $this->assertEquals('success', $got);

        $got = $this->model->get('not');
        $this->assertNull($got);
    }

    public function testHas()
    {
        $had = $this->model->has('has');
        $this->assertTrue($had);


        $had = $this->model->has('not');
        $this->assertFalse($had);
    }

    public function testSet()
    {
        $this->model->set('new', 'value');
        $this->model->set('has', 'had');

        $queryTable = $this->getConnection()->createQueryTable(
            'config', 'SELECT name, value FROM config'
        );
        $expectedTable = $this->createXMLDataSet(dirname(__FILE__)."/_fixtures/config.xml")
                              ->getTable("config");
        $this->assertTablesEqual($expectedTable, $queryTable);
    }
}
