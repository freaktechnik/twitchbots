<?php

use \Mini\Model\TypeCrawler\Storage\TypeCrawlerStorage;

class ShimTypeCrawlerStorage extends TypeCrawlerStorage
{
    private $lastCrawl;

    function __construct(int $forType)
    {
        parent::_construct($forType);
        $this->lastCrawl = 0;
    }

    public function get(string $prop): bool
    {
        return $prop == 'lastCrawl' ? $this->lastCrawl : null;
    }

    public function has(string $prop)
    {
        return $prop == 'lastCrawl';
    }

    public function set(string $prop, $val)
    {
        if($prop)
            $this->lastCrawl = $val;
        else
            parent::set($prop, $val);
    }
}
