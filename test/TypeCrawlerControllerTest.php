<?php

class TypeCrawlerControllerTest extends PHPUnit_Framework_TestCase
{
    public function testCrawl()
    {
        $storage = new \Mini\Model\TypeCrawler\Storage\StorageFactory('ShimTypeCrawlerStorage', array());
        $controller = new \Mini\Model\TypeCrawler\TypeCrawlerController($storage);
        $controller->registerCrawler('ShimTypeCrawler');

        $crawled = $controller->crawl('ShimTypeCrawler');
        $this->assertCount(1, $crawled);
        $this->assertInstanceOf('\\stdClass', $crawled[0]);
        $this->assertEquals('test', $crawled[0]->name);
        $this->assertEquals('crawl', $crawled[0]->channel);
        $this->assertEquals(42, $crawled[0]->type);
    }
}
