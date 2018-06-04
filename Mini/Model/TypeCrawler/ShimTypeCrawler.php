<?php

namespace Mini\Model\TypeCrawler;

use \Mini\Model\TypeCrawler\Storage\TypeCrawlerStorage;

class ShimTypeCrawler extends TypeCrawler
{
    /** @var int $type */
    public static $type = 42;

    function __construct(TypeCrawlerStorage $storage)
    {
        parent::__construct($storage);
    }

    /**
     * @inheritDoc
     */
    protected function doCrawl(): array
    {
        return [ $this->getBot('test', 'crawl') ];
    }
}
