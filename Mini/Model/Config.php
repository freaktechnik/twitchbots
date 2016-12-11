<?php

namespace Mini\Model;

/* CREATE TABLE IF NOT EXISTS config (
    name varchar(120) CHARACTER SET ascii NOT NULL,
    value varchar(100) CHARACTER SET ascii DEFAULT NULL,
    PRIMARY KEY (name)
) DEFAULT CHARSET=ascii */

class Config extends Store {
    function __construct(PingablePDO $db)
    {
        parent::__construct($db, "config");
    }

	public function get(string $key): string
	{
	    $query = $this->prepareSelect("value", "WHERE name=?");
	    $query->execute(array($key));
	    $result = $query->fetch();
	    if($result) {
    	    return $result->value;
    	}
	    else {
	        return "";
	    }
	}

	public function set(string $key, string $value)
	{
	    $query = $this->prepareUpdate("value=? WHERE name=?");
	    $query->execute(array($value, $key));
	}
}
