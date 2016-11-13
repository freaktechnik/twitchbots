<?php

/**
 * @coversDefaultClass \Mini\Model\TypeCrawler\Storage\StorageFactory
 */
class StorageFactoryTest extends PHPUnit_Framework_TestCase
{
    public function testGetStorage()
    {
        $factory = new \Mini\Model\TypeCrawler\Storage\StorageFactory('TypeCrawlerStorage', array());

        $storage = $factory->getStorage(2);

        $this->assertInstanceOf('\Mini\Model\TypeCrawler\Storage\TypeCrawlerStorage', $storage);
    }
}
