<?php

namespace Mini\Model;

use PDO;
use PDOStatement;
use Exception;

class PingablePDO {
    /** @var PDO $pdo */
    private $pdo;
    /** @var array $params */
    private $params;

    public function __construct(...$args)
    {
        $this->params = $args;
        $this->init();
    }

    /**
     * @return mixed
     */
    public function __call(string $name, array $args)
    {
        return $this->pdo->{$name}(...$args);
    }

    public function prepare(string $sql): PDOStatement
    {
        return $this->pdo->prepare($sql);
    }

    public function query(string $sql)
    {
        return $this->pdo->query($sql);
    }

    public function getOriginalPDO(): PDO
    {
        return $this->pdo;
    }

    public function ping(): void
    {
        try {
            $this->pdo->query("DO 1");
        } catch(Exception $e) {
            $this->init();
        }
    }

    public function init(): void
    {
        $this->pdo = new PDO(...$this->params);
        if($this->pdo instanceof PDO) {
            $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
    }
}
