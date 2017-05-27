<?php

use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Mini\Model\TypeCrawler\Storage\StorageFactory
 */
class StorageFactoryTest extends TestCase
{
    public function testGetStorage()
    {
        $factory = new \Mini\Model\TypeCrawler\Storage\StorageFactory('TypeCrawlerStorage', array());

        $storage = $factory->getStorage(2);

        $this->assertInstanceOf('\Mini\Model\TypeCrawler\Storage\TypeCrawlerStorage', $storage);
    }
}
