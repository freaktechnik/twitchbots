<?php

namespace Mini\Model;

use PDO;

include_once 'csrf.php';

class Model
{
    /**
     * The database connection
     * @var PDO
     */
	private $db;

	/**
	 * The page size
	 * @var int
	 */
	private $pageSize;

    /**
     * When creating the model, the configs for database connection creation are needed
     * @param $config
     */
    function __construct($config)
    {
        // PDO db connection statement preparation
        $dsn = 'mysql:host=' . $config['db_host'] . ';dbname='    . $config['db_name'] . ';port=' . $config['db_port'];

        // note the PDO::FETCH_OBJ, returning object ($result->id) instead of array ($result["id"])
        // @see http://php.net/manual/de/pdo.construct.php
        $options = array(PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ, PDO::ATTR_ERRMODE => PDO::ERRMODE_WARNING);

        // create new PDO db connection
        $this->db = new PDO($dsn, $config['db_user'], $config['db_pass'], $options);

        $this->pageSize = $config['page_size'];
	}

	public function getToken($formname)
	{
	    return generate_token($formname);
    }

    public function checkToken($formname, $token)
    {
        return validate_token($formname, $token);
    }

    public function addSubmission($username, $type, $description)
    {
        if($type == 0)
            $type = $description;

        $sql = "INSERT INTO submissions(name,description) VALUES (?,?)";
        $query = $this->db->prepare($sql);
        $query->execute(array($username, $type));
    }

    public function getSubmissions()
    {
        $sql = "SELECT name, description FROM submissions";
        $query = $this->db->prepare($sql);
        $query->execute();

        return $query->fetchAll();
    }

    public function getBotCount($type = 0)
    {
        $sql = "SELECT count FROM count";
        if($type != 0)
            $sql .= " WHERE count=?";

        $query = $this->db->prepare($sql);
        $query->execute(array($type));

        return $query->fetch()->count;
    }

    public function getBotPageCount() {
        return ceil($this->getBotCount() / (float)$this->pageSize);
    }

    public function getBots($page = 1)
    {
        if($page <= $this->getBotPageCount()) {
            $offset = ($page - 1) * $this->pageSize;
            $sql = "SELECT * FROM list LIMIT :start,:stop";
            $query = $this->db->prepare($sql);
            $query->bindValue(":start", $offset, PDO::PARAM_INT);
            $query->bindValue(":stop", $offset + $this->pageSize, PDO::PARAM_INT);
            $query->execute();
            return $query->fetchAll();
        }
        else {
            return array();
        }
    }

    public function getBot($name)
    {
        $sql = "SELECT * FROM bots WHERE name=?";
        $query = $this->db->prepare($sql);
        $query->execute(array($name));

        return $query->fetch();
    }

    public function getType($id)
    {
        $sql = "SELECT * FROM types WHERE id=?";
        $query = $this->db->prepare($sql);
        $query->bindValue(0, $id, PDO::PARAM_INT);
        $query->execute();

        return $query->fetch();
    }
}
