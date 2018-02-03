<?php

use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Mini\Model\TypeCrawler\TypeCrawlerController
 */
class TypeCrawlerControllerTest extends TestCase
{
    public function testCrawl()
    {
        $storage = new \Mini\Model\TypeCrawler\Storage\StorageFactory('ShimTypeCrawlerStorage', array());
        $controller = new \Mini\Model\TypeCrawler\TypeCrawlerController($storage);
        $controller->registerCrawler(\Mini\Model\TypeCrawler\ShimTypeCrawler::class);

        $crawled = $controller->crawl(42);
        $this->assertCount(1, $crawled);
        $this->assertInstanceOf(\Mini\Model\Bot::class, $crawled[0]);
        $this->assertEquals('test', $crawled[0]->name);
        $this->assertEquals('crawl', $crawled[0]->channel);
        $this->assertEquals(42, $crawled[0]->type);
    }
}
