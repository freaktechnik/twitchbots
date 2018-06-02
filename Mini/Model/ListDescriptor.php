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
    protected static $idType = PDO::PARAM_STR;

    protected $query = '';
    protected $tempTables = [];
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

    protected function getTempName(string $param): string
    {
        return 'tempvals'.$param;
    }

    /**
     * @return string[]
     */
    protected function addWhere(): array
    {
        $where = [];

        if($this->ids && count($this->ids)) {
            $this->tempTables['ids'] = self::$idType;
            $tableName = $this->getTempName('ids');

            $this->query .= ' INNER JOIN '.$tableName.' ON '.$tableName.'value = '.self::$idField;
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

    private function dbTypeFromPDO(int $pdoType): string
    {
        switch($pdoType) {
            case PDO::PARAM_INT:
                return "int(10)";
            default:
                return "varchar(535)";
        }
    }

    public function hasTempTables(): bool
    {
        return count($this->tempTables) > 0;
    }

    public function makeTempTables(PingablePDO $db)
    {
        $tableName;
        $value;
        $i;

        $sql = 'INSERT INTO ? (`value`,`index`) VALUES (?,?)';
        $query = $db->prepare($sql);
        $query->bindParam(1, $tableName);
        $query->bindParam(3, $i);
        foreach($this->tempTables as $name => $type) {
            $tableName = $this->getTempName($name);

            $sql = 'CREATE TEMPORARY TABLE '.$tableName.' (`value` '.$this->dbTypeFromPDO($type).' CHARACTER SET utf8 NOT NULL, `index` varchar(535) CHARACTER SET utf8 NOT NULL)';
            $createQ = $db->prepare($sql);
            $createQ->execute();

            $query->bindParam(2, $value, $type);
            foreach($this->{$name} as $i => $value) {
                $query->execute();
            }
        }
    }

    public function removeTempTables(): string
    {
        $sql = '';
        foreach($this->tempTables as $name => $type) {
            $sql .= "DROP TABLE IF EXISTS `".$this->getTempName($name)."`;";
        }
        return $sql;
    }

    public function reset()
    {
        $this->query = '';
        $this->params = [];
        $this->paramTypes = [];
        $this->tempTables = [];
        $this->queryBuilt = false;
    }
}
