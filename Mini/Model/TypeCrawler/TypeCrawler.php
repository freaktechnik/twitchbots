<?php

namespace Mini\Model\TypeCrawler;

use \Mini\Model\TypeCrawler\Storage\TypeCrawlerStorage;
use \Mini\Model\Bot;
use Exception;

class TypeCrawler {
    /** @var int $type */
    public static $type;
    /** @var TypeCrawlerStorage $storage */
    protected $storage;
    /** @var int $crawlInterval */
    protected static $crawlInterval = 3600;

    function __construct(TypeCrawlerStorage $storage) {
        $this->storage = $storage;
        if(!$this->storage->has('lastCrawl')) {
            $this->storage->set('lastCrawl', 0);
        }
    }

    /**
     * @return Bot[]
     */
    public function crawl(): array {
        if($this->shouldCrawl()) {
            $bots = $this->doCrawl();
            $this->storage->set('lastCrawl', time());
            return $bots;
        }
        else {
            return array();
        }
    }

    private function shouldCrawl(): bool {
        return (int)$this->storage->get('lastCrawl') + $this::$crawlInterval <= time();
    }

    /**
     * @codeCoverageIgnore
     * @return Bot[]
     */
    protected function doCrawl(): array {
        throw new Exception();
    }

    protected function getBot(string $name, $channel = null): Bot {
        $bot = new Bot;
        $bot->name = strtolower($name);
        $bot->type = $this::$type;
        $bot->channel = strtolower($channel);
        return $bot;
    }
}
