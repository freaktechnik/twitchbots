<?php

namespace Mini\Model\TypeCrawler\Storage;

use \Mini\Model\TypeCrawler\Storage\TypeCrawlerStorage;
use PDO;

class PDOStorage extends TypeCrawlerStorage {
    /** @var PDO */
    private $db;
    /** @var string */
    private $table;

    function __construct(int $forType, PDO $pdo, string $table) {
        parent::__construct($forType);

        $this->db = $pdo;
        $this->table = $table;
    }

    public function get(string $name) {
        $sql = "SELECT value FROM ".$this->table." WHERE name=?";
        $query = $this->db->prepare($sql);
        $query->execute(array($this->type."_".$name));

        $result = $query->fetch(PDO::FETCH_OBJ);
        return $result ? $result->value : null;
    }

    public function set(string $name, $value) {
        if($this->has($name))
            $sql = "UPDATE ".$this->table." SET value=? WHERE name=?";
        else
            $sql = "INSERT INTO ".$this->table." (value, name) VALUES (?,?)";
        $query = $this->db->prepare($sql);
        $query->execute(array($value, $this->type."_".$name));
    }

    public function has(string $name): bool {
        $sql = "SELECT value FROM ".$this->table." WHERE name=?";
        $query = $this->db->prepare($sql);
        $query->execute(array($this->type."_".$name));

        return $query->fetch(PDO::FETCH_OBJ) != null;
    }
}
