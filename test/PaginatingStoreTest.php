<?php

require_once "DBTestCase.php";

use \Mini\Model\PingablePDO;

/**
 * @coversDefaultClass \Mini\Model\PaginatingStore
 */
class PaginatingStoreTest extends DBTestCase
{
    /**
     * @var \Mini\Model\PaginatingStore
     */
    private $store;

    /**
     * @var int
     */
    const pageSize = 100;

    public function setUp()
    {
        $this->store = new \Mini\Model\PaginatingStore(self::$pdo, "config", self::pageSize);
        parent::setUp();
    }

    public function tearDown()
    {
        $this->store = null;
        parent::tearDown();
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
