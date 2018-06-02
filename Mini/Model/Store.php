<?php

namespace Mini\Model;

use PDOStatement;
use PDO;

class Store {
    /**
     * The database connection
     * @var PingablePDO $db
     */
    private $db;
    /**
     * @var string $table
     */
    private $table;

    function __construct(PingablePDO $db, string $table) {
        $this->db = $db;
        $this->table = $table;
    }

    public static function BindNullable(PDOStatement $query, $param, $value = null, int $type = PDO::PARAM_STR)
    {
        if($value === null) {
            $query->bindValue($param, $value, PDO::PARAM_NULL);
        }
        else {
            $query->bindValue($param, $value, $type);
        }
    }

    protected function prepareQuery(string $sql): PDOStatement
    {
        return $this->db->prepare($sql);
    }

    protected function prepareSelect(string $select = "*", string $where = "", string $table = null): PDOStatement
    {
        $table = $table ?? $this->table;
        if($where != "") {
            $where = " ".$where;
        }
        $sql = "SELECT ".$select." FROM `".$table."` AS `table`".$where;
        return $this->prepareQuery($sql);
    }

    protected function prepareDelete(string $condition): PDOStatement
    {
        $sql = "DELETE `table` FROM `".$this->table."` AS `table` ".$condition;
        return $this->prepareQuery($sql);
    }

    protected function prepareInsert(string $structure): PDOStatement
    {
        return $this->prepareQuery("INSERT INTO `".$this->table."` ".$structure);
    }

    protected function prepareUpdate(string $set): PDOStatement
    {
        return $this->prepareQuery("UPDATE `".$this->table."` SET ".$set);
    }

    public function prepareList(ListDescriptor $descriptor, string $fields = "`table`.*"): PDOStatement
    {
        $hasTempTables = $descriptor->hasTempTables();
        if($hasTempTables) {
            $descriptor->makeTempTables($this->db);
        }
        $query = $this->prepareSelect($fields, $descriptor->makeSQLQuery());
        $descriptor->bindParams($query);
        $query->execute();
        if($hasTempTables) {
            $this->prepareQuery($descriptor->removeTempTables())->execute();
        }
        return $query;
    }

    public function getCount(ListDescriptor $descriptor = null): int
    {
        if(!$descriptor) {
            $descriptor = new ListDescriptor();
        }
        $query = $this->prepareList($descriptor, "count(*) AS count");

        $query->setFetchMode(PDO::FETCH_CLASS, RowCount::class);
        $result = $query->fetch();
        if(is_bool($result)) {
            return 0;
        }
        /** @var RowCount $result */
        return $result->count;
    }

    public function getLastUpdate(string $condition = "", array $values = array()): int
    {
        $where = "ORDER BY date DESC LIMIT 1";
        if(!empty($condition)) {
            $where = "WHERE ".$condition." ".$where;
        }

        $query = $this->prepareSelect("date", $where);
        $query->execute($values);

        $query->setFetchMode(PDO::FETCH_CLASS, Row::class);
        /** @var Row $result */
        $result = $query->fetch();
        return strtotime($result->date);
    }

    public function getLastListUpdate(ListDescriptor $descriptor): int
    {
        $copy = clone $descriptor;
        $copy->orderBy = 'date';
        $copy->direction = ListDescriptor::DIR_DESC;
        $copy->limit = 1;
        $copy->offset = 0;

        $query = $this->prepareList($copy);
        $query->setFetchMode(PDO::FETCH_CLASS, Row::class);
        /** @var Row $result */
        $result = $query->fetch();
        return strtotime($result->date);
    }

    /**
     * Store the values and indexes from an array in a temporary table. Returns
     * the table name. The array indexes are in a column called "index" and the
     * values in a column called "value".
     *
     * @param string[] $values
     * @return string
     */
    protected function createTempTable(array $values): string
    {
        $tableName = "tempvals";
        $sql = 'CREATE TEMPORARY TABLE `'.$tableName.'` (`value` varchar(535) CHARACTER SET utf8 NOT NULL, `index` varchar(535) CHARACTER SET utf8 NOT NULL)';
        $query = $this->prepareQuery($sql);
        $query->execute();

        $sql = 'INSERT INTO `'.$tableName.'` (`value`,`index`) VALUES (?,?)';
        $query = $this->prepareQuery($sql);
        $value;
        $i;
        $query->bindParam(1, $value);
        $query->bindParam(2, $i);

        foreach($values as $i => $value) {
            $query->execute();
        }
        return $tableName;
    }

    protected function cleanUpTempTable(string $tableName)
    {
        $this->prepareQuery("DROP TABLE IF EXISTS `".$tableName."`")->execute();
    }

    protected function getLastInsertedId(): int
    {
        $query = $this->prepareSelect("LAST_INSERT_ID() as lid");
        $query->execute();
        /** @var \stdClass $result */
        $result = $query->fetch();
        return (int)$result->lid;
    }
}
