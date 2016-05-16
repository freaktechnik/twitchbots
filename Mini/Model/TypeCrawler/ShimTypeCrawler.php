<?php

namespace \Mini\Model\TypeCrawler;

class ShimTypeCrawler extends TypeCrawler
{
    public static $type = 42;

    function __construct(TypeCrawlerStorage $storage) {
        parent::__construct($storage);
    }

    protected function doCrawl(): array
    {
        return array($this->getBot('test', 'crawl'));
    }
}
