<?php

namespace Mini\Model\TypeCrawler;

use \Mini\Model\TypeCrawler\Storage\StorageFactory;

//TODO disable crawlers if the type is disabled in the DB.
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
        $this->registerCrawler('DeepBot');
    }

    private function getClassName(string $crawler): string {
        return '\\Mini\\Model\\TypeCrawler\\'.$crawler;
    }

    public function registerCrawler(string $crawler) {
        $crawler = $this->getClassName($crawler);
        $this->crawlers[] = new $crawler($this->storage->getStorage($crawler::$type));
    }

    public function crawl(int $type): array {
        foreach($this->crawlers as $c) {
            if($c::$type == $type) {
                $crawler = $c;
                break;
            }
        }
        return $crawler->crawl();
    }

    public function triggerCrawl(): array {
        $ret = array();
        foreach($this->crawlers as $crawler) {
            try {
                $crawlResult = $crawler->crawl();
            } catch(Exception $e) {
                continue;
            }
            $ret = array_merge($ret, $crawlResult);
        }
        return $ret;
    }
}
