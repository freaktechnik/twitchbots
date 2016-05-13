<?php

namespace Mini\Model\TypeCrawler\Storage;

use TypeCrawlerStorage;
use PDO;

class PDOStorage extends TypeCrawlerStorage {
    private PDO $db
    private string $table

    __construct(int $forType, PDO $pdo, string $table) {
        parent::__construct($forType);

        $this->db = $pdo;
        $this->table = $table;
    }

    public function get(string $name) {
        $sql = "SELECT value FROM ".$this->table." WHERE name=?";
        $query = $this->db->prepare($sql);
        $query->execute(array($this->type."_".$name));

        return $query->fetch()->value;
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
        return $this->get($name) != null;
    }
}
