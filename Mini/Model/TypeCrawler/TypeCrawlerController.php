<?php

namespace Mini\Model\TypeCrawler;

use \Mini\Model\TypeCrawler\Storage\StorageFactory;

class TypeCrawlerController {
    /** @var array */
    private $crawlers;
    /** @var StorageFactory */
    private $storage;

    function __construct(StorageFactory $storageFactory) {
        $this->crawlers = array();
        $this->storage = $storageFactory;

        $this->registerCrawler('ModBot');
        $this->registerCrawler('Pajbot');
    }

    private function getClassName(string $crawler): string {
        return '\\Mini\\Model\\TypeCrawler\\'.$crawler;
    }

    public function registerCrawler(string $crawler) {
        $crawler = $this->getClassName($crawler);
        $this->crawlers[] = new $crawler($this->storage->getStorage($crawler::$type));
    }

    public function triggerCrawl(): array {
        $ret = array();
        foreach($this->crawlers as $crawler) {
            $ret = array_merge($ret, $crawler->crawl());
        }
        return $ret;
    }
}
