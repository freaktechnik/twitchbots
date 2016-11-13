<?php

use \Mini\Model\TypeCrawler\Storage\TypeCrawlerStorage;

/**
 * @coversDefaultClass \Mini\Model\TypeCrawler\Storage\TypeCrawlerStorage
 */
class TypeCrawlerStorageTest extends PHPUnit_Framework_TestCase
{
    /**
     * @expectedException Exception
     * @covers ::get
     */
    public function testGetThrows()
    {
        $storage = new TypeCrawlerStorage(1);
        $storage->get('test');
    }

    /**
     * @expectedException Exception
     * @covers ::has
     */
    public function testHasThrows()
    {
        $storage = new TypeCrawlerStorage(1);
        $storage->has('test');
    }

    /**
     * @expectedException Exception
     * @covers ::set
     */
    public function testSetThrows()
    {
        $storage = new TypeCrawlerStorage(1);
        $storage->set('test', 'value');
    }
}
