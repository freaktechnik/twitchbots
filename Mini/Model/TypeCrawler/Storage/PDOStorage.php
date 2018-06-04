<?php

namespace Mini\Model\TypeCrawler\Storage;

use \Mini\Model\TypeCrawler\Storage\TypeCrawlerStorage;
use \Mini\Model\PingablePDO;
use PDO;
use \Mini\Model\ConfigItem;

class PDOStorage extends TypeCrawlerStorage {
    /** @var PingablePDO $db */
    private $db;
    /** @var string $table */
    private $table;

    function __construct(int $forType, PingablePDO $pdo, string $table)
    {
        parent::__construct($forType);

        $this->db = $pdo;
        $this->table = $table;
    }

    /**
     * @inheritDoc
     */
    public function get(string $name)
    {
        $sql = "SELECT value FROM ".$this->table." WHERE name=?";
        $query = $this->db->prepare($sql);
        $query->execute(array($this->type."_".$name));

        $query->setFetchMode(PDO::FETCH_CLASS, ConfigItem::class);
        /** @var ConfigItem $result */
        $result = $query->fetch();
        return $result ? $result->value : null;
    }

    /**
     * @inheritDoc
     */
    public function set(string $name, $value)
    {
        $this->db->ping();
        if($this->has($name)) {
            $sql = "UPDATE ".$this->table." SET value=? WHERE name=?";
        }
        else {
            $sql = "INSERT INTO ".$this->table." (value, name) VALUES (?,?)";
        }
        $query = $this->db->prepare($sql);
        $query->execute([ $value, $this->type."_".$name ]);
    }

    public function has(string $name): bool
    {
        $this->db->ping();
        $sql = "SELECT value FROM ".$this->table." WHERE name=?";
        $query = $this->db->prepare($sql);
        $query->execute(array($this->type."_".$name));

        return $query->fetch(PDO::FETCH_OBJ) != null;
    }
}
