<?php

namespace Mini\Model;

use PDO;

class Bots extends PaginatingStore {
    function __construct(PingablePDO $db, int $pageSize = 50)
    {
        parent::__construct($db, "bots", $pageSize);
    }

    public function getLastUpdateByType(int $type = 0): int
    {
        $condition = "";
        if($type != 0) {
            $condition = "type=?";
        }
        return $this->getLastUpdate($condition, array($type));
    }

    public function getCount(int $type = 0): int
    {
        $where = "";
        if($type != 0) {
            $where = "WHERE type=?";
        }

        $query = $this->prepareSelect("count(*) as count", $where);
        $query->execute(array($type));

        return (int)$query->fetch()->count;
    }

    public function getBots(int $page = 1): array
    {
        if($page <= $this->getPageCount($this->pageSize)) {
            $query = $this->prepareSelect("*", "LIMIT :start,:stop", "list");
            $this->doPagination($query, $this->getOffset($page));
            $query->execute();
            return $query->fetchAll();
        }
        else {
            return array();
        }
    }

    public function getAllRawBots(int $offset = 0, int $limit = null): array
    {
        $limit = $limit ?? $this->pageSize;
        if($limit > 0 && $offset < $this->getCount()) {
            $query = $this->prepareSelect("*", "LIMIT :start,:stop");
            $this->doPagination($query, $offset, $limit);
            $query->execute();
            return $query->fetchAll();
        }
        else {
            return array();
        }
    }

    public function getBotsByNames(array $names, int $offset = 0, int $limit = null): array
    {
        $limit = $limit ?? $this->pageSize;
        $namesCount = count($names);
        if($limit > 0 && $offset < $namesCount) {
            $tempTable = $this->createTempTable($names);

            $query = $this->prepareSelect('*', 'INNER JOIN '.$tempTable.' ON table.name = '.$tempTable.'.value LIMIT ?,?');
            $this->doPagination($query, $offset, $limit, 1, 2);
            $query->execute();

            $result = $query->fetchAll();
            $this->cleanUpTempTable($tempTable);

            return $result;
        }
        else {
            return array();
        }
    }

    public function getBotsByType(int $type, int $offset = 0, int $limit = null): array
    {
        $limit = $limit ?? $this->pageSize;
        //TODO should these bounds checks be in the controller?
        if($limit > 0 && $offset < $this->getCount($type)) {
            $query = $this->prepareSelect("*", "WHERE type=:type LIMIT :start,:stop");
            $this->doPagination($query, $offset, $limit);
            $query->bindValue(":type", $type, PDO::PARAM_INT);
            $query->execute();
            return $query->fetchAll();
        }
        else {
            return array();
        }
    }

    public function getBot(string $name)
    {
        $query = $this->prepareSelect("*", "WHERE name=?");
        $query->execute(array($name));

        return $query->fetch();
    }


    public function removeBot(string $username)
    {
        $query = $this->prepareDelete("WHERE name=?");
        $query->execute(array($username));
    }

    public function removeBots(array $usernames)
    {
        $tempTable = $this->createTempTable($usernames);
        $where = 'INNER JOIN '.$tempTable.' AS t ON t.value = table.name';
        $query = $this->prepareDelete($where);
        $query->execute();
        $this->cleanUpTempTable($tempTable);
    }

    public function addBot(string $name, int $type, $channel = null, $query = null)
    {
        $structure = "(name,type,channel) VALUES (?,?,?)";
        $query = $this->prepareInsert($structure);
        $query->bindValue(1, strtolower($name), PDO::PARAM_STR);
        $query->bindValue(2, $type, PDO::PARAM_INT);
        $query->bindValue(3, strtolower($channel), PDO::PARAM_STR);
        $query->execute();
    }

    public function getBotsByChannel(string $channel): array
    {
        $where = "WHERE channel=?";
        $query = $this->prepareSelect("*", $where);
        $query->execute(array($channel));

        return $query->fetchAll();
    }

    public function getOldestBots(int $count = 10): array
    {
        $query = $this->prepareSelectt("*", "WHERE date < DATE_SUB(NOW(), INTERVAL 24 HOUR)  ORDER BY date ASC LIMIT ?");
        $query->execute(array($count));

        return $query->fetchAll();
    }

    public function touchBot($username)
    {
        $query = $this->prepareUpdate("date=NOW() WHERE name=?");
        $query->execute(array($username));
    }
}
