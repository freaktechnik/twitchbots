<?php

namespace Mini\Model;

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
    /** @var string[] */
    public $ids = [];

    protected static $idField = 'id';

    private $query = '';
    private $params = [];

    function __constructor() {
        $this->ids = [];
        $this->reset();
    }

    private function addLimit()
    {
        if($this->limit) {

            if($this->offset > 0) {
                $this->query .= ' LIMIT ?, ?';
                $this->params[] = $this->offset;
                $this->params[] = $this->offset + $this->limit;
            }
            else {
                $this->query .= ' LIMIT ?';
                $this->params[] = $this->limit;
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

        if(count($this->ids)) {
            $this->params = array_merge($this->params, $this->ids);
            $conditions = array_fill(0, count($this->ids), 'table.'.self::$idField.' = ?');
            $where[] = '('.implode(' OR ', $conditions).')';
        }

        return $where;
    }

    public function makeSQLQuery(): string
    {
        $where = $this->addWhere();
        if(count($where)) {
            $this->query .= ' WHERE '.implode(' AND ', $where);
        }
        $this->addOrder();
        $this->addLimit();

        return $this->query;
    }

    public function getParams(): array
    {
        return $this->params;
    }

    public function reset()
    {
        $this->params = [];
    }
}
