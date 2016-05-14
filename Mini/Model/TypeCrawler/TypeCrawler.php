<?php

namespace Mini\Model\TypeCrawler;

use \Mini\Model\TypeCrawler\Storage\TypeCrawlerStorage;

class TypeCrawler {
    /** @var int */
    public static $type;
    /** @var TypeCrawlerStorage */
    protected $storage;
    /** @var int */
    protected static $crawlInterval = 3600;

    function __construct(TypeCrawlerStorage $storage) {
        $this->storage = $storage;
        if(!$this->storage->has('lastCrawl'))
            $this->storage->set('lastCrawl', 0);
    }

    public function crawl(): array {
        if($this->shouldCrawl()) {
            $bots = $this->doCrawl();
            $this->storage->set('lastCrawl', time());
            return $bots;
        }
        else
            return array();
    }

    private function shouldCrawl(): bool {
        return (int)$this->storage->get('lastCrawl') + $this::$crawlInterval <= time();
    }

    protected function doCrawl(): array {
        throw new Execption();
    }
}
