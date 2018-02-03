<?php

namespace Mini\Model\TypeCrawler\Storage;

use Exception;

class TypeCrawlerStorage {
    /** @var int $type */
    protected $type;

    function __construct(int $forType) {
        $this->type = $forType;
    }

    /**
     * @codeCoverageIgnore
     * @return mixed
     */
    public function get(string $name) {
        throw new Exception();
    }

    /**
     * @codeCoverageIgnore
     * @param string $name
     * @param mixed $value
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
