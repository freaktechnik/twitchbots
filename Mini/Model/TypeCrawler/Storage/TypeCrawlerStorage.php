<?php

namespace Mini\Model\TypeCrawler\Storage;

use Exception;

class TypeCrawlerStorage {
    /** @var in */
    protected $type;

    function __construct(int $forType) {
        $this->type = $forType;
    }

    public function get(string $name) {
        throw new Exception();
    }

    public function set(string $name, $value) {
        throw new Exception();
    }

    public function has(string $name) {
        throw new Exception();
    }
}
