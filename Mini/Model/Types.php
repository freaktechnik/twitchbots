<?php

namespace Mini\Model;

use PDO;

/* CREATE TABLE IF NOT EXISTS types (
    id int(10) unsigned NOT NULL AUTO_INCREMENT,
    name varchar(255) CHARACTER SET utf8 NOT NULL,
    multichannel tinyint(1) NOT NULL,
    url text CHARACTER SET ascii DEFAULT NULL,
    managed tinyint(1) NOT NULL,
    customUsername tinyint(1) DEFAULT NULL,
    identifiableby text DEFAULT NULL,
    description text DEFAULT NULL,
    date timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    enabled boolean DEFAULT 1,
    sourceUrl text DEFAULT NULL,
    commandsUrl text DEFAULT NULL,
    payment int(2) DEFAULT NULL,
    hasFreeTier tinyint(1) DEFAULT 1,
    apiVersion int(10) DEFAULT NULL,
    channelsEstimate int(10) DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY name (name)
) DEFAULT CHARSET=utf8 */

class Types extends PaginatingStore {
    const API_VERSIONS = [
        'jtv',
        'kraken v1',
        'kraken v3',
        'kraken v5',
        'helix'
    ];

    const PAYMENT = [
        'Free',
        'Lifetime license',
        'Subscription'
    ];

    function __construct(PingablePDO $db, int $pageSize = 50)
    {
        parent::__construct($db, "types", $pageSize);
    }

    /**
     * @return Type|bool
     */
    public function getType(int $id)
    {
        $sql = "SELECT * FROM types WHERE id=?";
        $query = $this->prepareSelect("*", "WHERE id=?");
        $query->bindValue(1, $id, PDO::PARAM_INT);
        $query->execute();

        $query->setFetchMode(PDO::FETCH_CLASS, Type::class);
        return $query->fetch();
    }

    public function getTypeOrThrow(int $id): Type
    {
        $type = $this->getType($id);
        if(!$type) {
            throw new \Exception("Type does not exist");
        }
        return $type;
    }

    /**
     * Only returns enabled types.
     *
     * @return Type[]
     */
    public function getAllTypes(string $orderBy = 'count'): array
    {
        $query = $this->prepareSelect("`table`.*, COUNT(DISTINCT(bots.name)) AS count", "LEFT JOIN bots on bots.type = table.id WHERE enabled=1 GROUP BY table.id ORDER BY ? DESC, table.name ASC");
        $query->execute([ $orderBy ]);

        $query->setFetchMode(PDO::FETCH_CLASS, Type::class);
        return $query->fetchAll();
    }

    /**
     * @return Type[]
     */
    public function getTypes(int $page = 1, bool $showDisabled = false): array
    {
        if($page <= $this->getPageCount($this->pageSize)) {
            $where = '';
            if(!$showDisabled) {
                $where = 'WHERE table.enabled=1';
            }
            $query = $this->prepareSelect("`table`.*, COUNT(DISTINCT(bots.name)) AS count", "LEFT JOIN bots on bots.type = `table`.id $where GROUP BY `table`.id ORDER BY `table`.name ASC LIMIT :start,:stop");
            $this->doPagination($query, $this->getOffset($page));
            $query->execute();
            $query->setFetchMode(PDO::FETCH_CLASS, Type::class);
            return $query->fetchAll();
        }
        return [];
    }

    public function addType(
        string $name,
        bool $multichannel,
        bool $managed,
        bool $customUsername,
        string $identifyableBy,
        string $description,
        string $url = null,
        string $sourceUrl = null,
        string $commandsUrl = null,
        int $payment = null,
        bool $hasFreeTier = true,
        int $apiVersion = null
    ): int
    {
        $sql = "(name,multichannel,managed,customUsername,identifiableby,description,url,sourceUrl,commandsUrl,payment,hasFreeTier,apiVersion) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)";
        $query = $this->prepareInsert($sql);
        $query->execute([
            $name,
            $multichannel ? 1 : 0,
            $managed ? 1 : 0,
            $customUsername ? 1 : 0,
            $identifyableBy,
            $description,
            $url ?? null,
            $sourceUrl ?? null,
            $commandsUrl ?? null,
            $payment,
            $hasFreeTier,
            $apiVersion
        ]);
        return $this->getLastInsertedId();
    }

    public function setEstimate(int $id, int $count)
    {
        $query = $this->prepareUpdate('channelsEstimate=? WHERE id=?');
        $query->execute([ $count, $id ]);
    }

    /**
     * @return Type[]
     */
    public function getTop(int $count): array
    {
        $query = $this->prepareSelect("`table`.*", "WHERE enabled=1 ORDER BY channelsEstimate DESC, table.name ASC LIMIT ?");
        $query->execute([ $count ]);

        $query->setFetchMode(PDO::FETCH_CLASS, Type::class);
        return $query->fetchAll();
    }
}
