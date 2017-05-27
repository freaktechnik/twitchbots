<?php

require_once "DBTestCase.php";

use \Mini\Model\PingablePDO;

/**
 * @coversDefaultClass \Mini\Model\TypeCrawler\Storage\PDOStorage
 */
class PDOStorageTest extends DBTestCase
{
    protected static $dataSet = 'pdostorage';
    protected static $tables = ['config'];

    /**
     * @var \Mini\Model\TypeCrawler\Storage\PDOStorage
     */
    private $model;

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
