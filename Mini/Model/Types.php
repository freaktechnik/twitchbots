<?php

namespace Mini\Model;

use PDO;

/* CREATE TABLE IF NOT EXISTS types (
    id int(10) unsigned NOT NULL AUTO_INCREMENT,
    name varchar(255) CHARACTER SET utf8 NOT NULL,
    multichannel tinyint(1) NOT NULL,
    url text CHARACTER SET ascii NOT NULL,
    managed tinyint(1) NOT NULL,
    customUsername tinyint(1) DEFAULT NULL,
    identifiableby text DEFAULT NULL,
    description text DEFAULT NULL,
    date timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    enabled boolean DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY name (name)
) DEFAULT CHARSET=utf8 */

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

    /**
     * Only returns enabled types.
     */
    public function getAllTypes(): array
    {
        $query = $this->prepareSelect("`table`.*, COUNT(DISTINCT(bots.name)) AS count", "LEFT JOIN bots on bots.type = table.id WHERE enabled=1 GROUP BY table.id ORDER BY count DESC, table.name ASC");
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

    public function addType(string $name, string $url, bool $multichannel, bool $managed, bool $customUsername, string $identifyableBy, string $description): int
    {
        $sql = "(name,url,multichannel,managed,customUsername,identifiableby,description) VALUES (?,?,?,?,?,?,?)";
        $query = $this->prepareInsert($sql);
        $query->execute([
            $name,
            $url,
            $multichannel ? 1 : 0,
            $managed ? 1 : 0,
            $customUsername ? 1 : 0,
            $identifyableBy,
            $description
        ]);
        return (int)$this->getLastInsertedId();
    }
}
