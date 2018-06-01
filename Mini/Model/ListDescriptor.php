<?php

namespace Mini\Model;

use \PDO;

class ListDescriptor
{
    const DIR_ASC = 1;
    const DIR_DESC = 2;

    /** @var int */
    public $offset = 0;
    /** @var int */
    public $limit;
    /** @var int */
    public $direction = self::DIR_DESC;
    /** @var string */
    public $orderBy;
    /** @var string[]|null */
    public $ids = [];

    protected static $idField = 'id';

    protected $query = '';
    private $params = [];
    private $paramTypes = [];
    private $queryBuilt = false;

    function __constructor() {
        $this->ids = [];
        $this->reset();
    }

    protected function addParam($value, int $type = PDO::PARAM_STR) {
        $this->params[] = $value;
        $this->paramType[count($this->params) - 1] = $type;
    }

    private function addLimit()
    {
        if($this->limit) {

            if($this->offset > 0) {
                $this->query .= ' LIMIT ?,?';
                $this->addParam($this->offset, PDO::PARAM_INT);
                $this->addParam($this->limit, PDO::PARAM_INT);
            }
            else {
                $this->query .= ' LIMIT ?';
                $this->addParam($this->limit, PDO::PARAM_INT);
            }
        }
    }

    private function addOrder()
    {
        if($this->orderBy) {
            $this->query .= ' ORDER BY '.$this->orderBy;
            if($this->direction == self::DIR_ASC) {
                $this->query .= ' ASC';
            }
            else {
                $this->query .= ' DESC';
            }
        }
    }

    /**
     * @return string[]
     */
    protected function addWhere(): array
    {
        $where = [];

        if($this->ids && count($this->ids)) {
            foreach($this->ids as $id) {
                $this->addParam($id);
            }
            $conditions = array_fill(0, count($this->ids), self::$idField.' = ?');
            $where[] = '('.implode(' OR ', $conditions).')';
        }

        return $where;
    }

    public function makeSQLQuery(): string
    {
        if(!$this->queryBuilt) {
            $where = $this->addWhere();
            if(count($where)) {
                $this->query .= ' WHERE '.implode(' AND ', $where);
            }
            $this->addOrder();
            $this->addLimit();
            $this->queryBuilt = true;
        }

        return $this->query;
    }

    public function bindParams(\PDOStatement $query)
    {
        foreach($this->params as $i => $value) {
            $type = $this->paramTypes[$i] ?? PDO::PARAM_STR;
            Store::BindNullable($query, $i + 1, $value, $type);
        }
    }

    public function reset()
    {
        $this->query = '';
        $this->params = [];
        $this->paramTypes = [];
        $this->queryBuilt = false;
    }
}
