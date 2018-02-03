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

    public function getCount(): int
    {
        $query = $this->prepareSelect("count(*) AS count");
        $query->execute();

        $query->setFetchMode(PDO::FETCH_CLASS, RowCount::class);
        /** @var RowCount $result */
        $result = $query->fetch();
        return (int)$result->count;
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

    /**
     * Store the values and indexes from an array in a temporary table. Returns
     * the table name. The array indexes are in a column called "index" and the
     * values in a column called "value".
     *
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
