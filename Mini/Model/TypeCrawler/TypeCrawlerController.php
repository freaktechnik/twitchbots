<?php

namespace Mini\Model\TypeCrawler;

use \Mini\Model\TypeCrawler\Storage\StorageFactory;

//TODO disable crawlers if the type is disabled in the DB.
class TypeCrawlerController {
    /** @var TypeCrawler[] */
    private $crawlers = [];
    /** @var StorageFactory */
    private $storage;

    function __construct(StorageFactory $storageFactory)
    {
        $this->storage = $storageFactory;

        $this->registerCrawler(ModBot::class);
        $this->registerCrawler(Pajbot::class);
        $this->registerCrawler(DeepBot::class);
        $this->registerCrawler(FrankerFaceZ::class);
    }

    public function registerCrawler(string $crawler): void
    {
        $this->crawlers[] = new $crawler($this->storage->getStorage($crawler::$type));
    }

    /**
     * @return \Mini\Model\Bot[]
     */
    public function crawl(int $type): array
    {
        foreach($this->crawlers as $c) {
            if($c::$type == $type) {
                $crawler = $c;
                break;
            }
        }
        if($crawler instanceof TypeCrawler) {
            return $crawler->crawl();
        }
        return [];
    }

    /**
     * @return \Mini\Model\Bot[]
     */
    public function triggerCrawl(): array
    {
        $ret = [];
        foreach($this->crawlers as $crawler) {
            try {
                $crawlResult = $crawler->crawl();
            }
            catch(\Exception $e) {
                continue;
            }
            $ret = array_merge($ret, $crawlResult);
        }
        return $ret;
    }
}
