<?php

include_once('_fixtures/setup.php');

use \Mini\Model\PingablePDO;
use PHPUnit\Framework\TestCase;
use PHPUnit\DbUnit\TestCaseTrait;

/**
 * @coversDefaultClass \Mini\Model\TypeCrawler\Storage\PDOStorage
 */
class PDOStorageTest extends TestCase
{
    use TestCaseTrait;

    // Database connection efficieny
    static private $pdo = null;
    private $conn = null;

    /**
     * @var \Mini\Model\TypeCrawler\Storage\PDOStorage
     */
    private $model;

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
        $this->model = new \Mini\Model\TypeCrawler\Storage\PDOStorage(1, self::$pdo, 'config');
        parent::setUp();
    }

    public function tearDown()
    {
        $this->model = null;
        parent::tearDown();
    }

    /**
     * @covers ::get
     */
    public function testGet()
    {
        $got = $this->model->get('get');
        $this->assertEquals('success', $got);

        $got = $this->model->get('not');
        $this->assertNull($got);
    }

    /**
     * @covers ::has
     */
    public function testHas()
    {
        $had = $this->model->has('has');
        $this->assertTrue($had);


        $had = $this->model->has('not');
        $this->assertFalse($had);
    }

    /**
     * @covers ::set
     */
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
