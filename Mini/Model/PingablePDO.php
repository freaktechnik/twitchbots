<?php

namespace Mini\Model;

use ReflectionClass;

class PingablePDO {
    private $pdo;
    private $params;

    public function __construct()
    {
        $this->params = func_get_args();
        $this->init();
    }

    public function __call(string $name, array $args)
    {
        return call_user_func_array(array($this->pdo, $name), $args);
    }
    
    public function getOriginalPDO() {
        return $this->pdo;
    }

    public function ping()
    {
        try {
            $this->pdo->query("DO 1");
        } catch(Exception $e) {
            $this->init();
        }
    }

    public function init()
    {
        $class = new ReflectionClass('PDO');
        $this->pdo = $class->newInstanceArgs($this->params);
    }
}

