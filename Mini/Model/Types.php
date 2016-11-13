<?php

namespace Mini\Model;

use PDO;

class Types extends PaginatingStore {
    function __construct(PingablePDO $db, int $pageSize = 50)
    {
        parent::__construct($db, "types", $pageSize);
    }

    public function getType(int $id)
    {
        $sql = "SELECT * FROM types WHERE id=?";
        $query = $this->prepareSelect("*", "WHERE id=?");
        $query->bindValue(1, $id, PDO::PARAM_INT);
        $query->execute();

        return $query->fetch();
    }

    public function getAllTypes(): array
    {
        $query = $this->prepareSelect("`table`.*, COUNT(DISTINCT(bots.name)) AS count", "LEFT JOIN bots on bots.type = table.id GROUP BY table.id ORDER BY count DESC, table.name ASC");
        $query->execute();

        return $query->fetchAll();
    }

    public function getTypes($page = 1): array
    {
        if($page <= $this->getPageCount($this->pageSize)) {
            $query = $this->prepareSelect("`table`.*, COUNT(DISTINCT(bots.name)) AS count", "LEFT JOIN bots on bots.type = `table`.id GROUP BY `table`.id ORDER BY `table`.name ASC LIMIT :start,:stop");
            $this->doPagination($query, $this->getOffset($page));
            $query->execute();
            return $query->fetchAll();
        }
        else {
            return array();
        }
    }
}
