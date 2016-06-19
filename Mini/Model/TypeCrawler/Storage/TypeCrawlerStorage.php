<?php

namespace Mini\Model\TypeCrawler\Storage;

use Exception;

class TypeCrawlerStorage {
    /** @var in */
    protected $type;

    function __construct(int $forType) {
        $this->type = $forType;
    }

    /**
     * @codeCoverageIgnore
     */
    public function get(string $name) {
        throw new Exception();
    }

    /**
     * @codeCoverageIgnore
     */
    public function set(string $name, $value) {
        throw new Exception();
    }

    /**
     * @codeCoverageIgnore
     */
    public function has(string $name) {
        throw new Exception();
    }
}
