<?php

namespace Mini\Model;

use PDOStatement;

class PaginatingStore extends Store {
    /**
     * The default page size
     * @var int
     */
    private $pageSize;

    function __construct(PingablePDO $db, string $table, int $pageSize = 50) {
        $this->pageSize = $pageSize;
        parent::__construct($db, $table);
    }

    public function getLastUpdate(string $condition, array $values): int
    {
        $where = "ORDER BY date DESC LIMIT 1";
        if(!empty($condition)) {
            $where = "WHERE ".$condition." ".$where;
        }

        $query = $this->prepareSelect("date", $where);
        $query->execute($values);

        return strtotime($query->fetch()->date);
    }

    public function getCount(): int
    {
        $query = $this->prepareSelect("count(*) AS count");
        $query->execute();

        return (int)$query->fetch()->count;
    }

    public function getPageCount($limit = null, $count = null): int
    {
        $limit = $limit ?? $this->pageSize;
        $count = $count ?? $this->getCount();
        if($limit > 0) {
            return ceil($count / (float)$limit);
        }
        else {
            return 0;
        }
    }

    public function getOffset(int $page): int
    {
        return ($page - 1) * $this->pageSize;
    }

    protected function doPagination(PDOStatement $query, int $offset = 0, int $limit = null, $start = ":start", $stop = ":stop")
    {
        $limit = $limit ?? $this->pageSize;
        $query->bindValue($start, $offset, PDO::PARAM_INT);
        $query->bindValue($stop, $limit, PDO::PARAM_INT);
    }
}
