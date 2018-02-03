<?php

namespace Mini\Model\TypeCrawler\Storage;

class ShimTypeCrawlerStorage extends TypeCrawlerStorage
{
    /** @var int $lastCrawl */
    private $lastCrawl;

    function __construct(int $forType)
    {
        parent::__construct($forType);
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
