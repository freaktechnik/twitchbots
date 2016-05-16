<?php

use \Mini\Model\TypeCrawler\Storage\TypeCrawlerStorage;

class TypeCrawlerStorageTest extends PHPUnit_Framework_TestCase
{
    /**
     * @expectedException Exception
     */
    public function testGetThrows()
    {
        $storage = new TypeCrawlerStorage(1);
        $storage->get('test');
    }

    /**
     * @expectedException Exception
     */
    public function testHasThrows()
    {
        $storage = new TypeCrawlerStorage(1);
        $storage->has('test');
    }

    /**
     * @expectedException Exception
     */
    public function testSetThrows()
    {
        $storage = new TypeCrawlerStorage(1);
        $storage->set('test');
    }
}
