<?php

namespace Mini\Model;

/* CREATE TABLE IF NOT EXISTS confirmed_people (
    twitch_id varchar(20) DEFAULT NULL COMMENT `Twitch user ID`,
    date timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT `Last content modification ts`,
    PRIMARY KEY (twitch_id)
) DEFAULT CHARSET=utf8 */

class ConfirmedPeople extends Store {
    function __construct(PingablePDO $db)
    {
        parent::__construct($db, "confrimed_people");
    }

    public function getAll(): array {
        $query = $this->prepareSelect("`table`.twitch_id");
        $query->execute();

        return $query->fetchAll();
    }

    public function has(string $id)
    {
        $where = "WHERE twitch_id=?";
        $params = array($id);

        $query = $this->prepareSelect("*", $where);
        $query->execute($params);

        return !empty($query->fetch());
    }

    public function add(string $twitch_id) {
        $sql = "(twitch_id) VALUES (?)";
        $query = $this->prepareInsert($sql);
        $query->execute([
            $twitch_id
        ]);
    }
}
