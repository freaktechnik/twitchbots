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
        $storageClass = $this->getClassName($this->storage);
        return new $storageClass($type, ...$this->additionalArgs);
    }
}
