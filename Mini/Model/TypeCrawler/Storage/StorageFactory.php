<?php

namespace Mini\Model\TypeCrawler\Storage;

class StorageFactory {
    /** @var string */
    private $storage;
    /** @var array */
    private $additionalArgs;

    function __construct(string $storage, array $additionalArgs) {
        $this->storage = $storage;
        $this->additionalArgs = $additionalArgs;
    }

    private function getClassName(string $name): string {
        return '\\Mini\\Model\\TypeCrawler\\Storage\\'.$name;
    }

    public function getStorage(int $type): TypeCrawlerStorage {
        return new $this->getClassName($this->storage)($type, ...$this->additionalArgs);
    }
}
