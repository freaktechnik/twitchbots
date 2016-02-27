<?php

namespace Mini\Model;

use PDO;
use PDOStatement;
use Exception;

require __DIR__.'/../../vendor/autoload.php';
include_once 'csrf.php';

class Model
{
    /**
     * The database connection
     * @var PDO
     */
	private $db;

	/**
	 * The default page size
	 * @var int
	 */
	private $pageSize;

	/**
	 * The Twitch API wrapper
	 * @var \ritero\SDK\TwitchTV\TwitchSDK
	 */
    private $twitch;

    /**
     * When creating the model, the configs for database connection creation are needed
     * @param $config
     */
    function __construct(array $config)
    {
        // PDO db connection statement preparation
        $dsn = 'mysql:host=' . $config['db_host'] . ';dbname=' . $config['db_name'] . ';port=' . $config['db_port'];

        // note the PDO::FETCH_OBJ, returning object ($result->id) instead of array ($result["id"])
        // @see http://php.net/manual/de/pdo.construct.php
        $options = array(PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ, PDO::ATTR_ERRMODE => PDO::ERRMODE_WARNING);

        // create new PDO db connection
        $this->db = new PDO($dsn, $config['db_user'], $config['db_pass'], $options);

        $this->pageSize = $config['page_size'];

        if(!$this->getConfig('update_size'))
            $this->setConfig('update_size', '10');

        if(array_key_exists('testing', $config) && $config['testing']) {
            $this->twitch = new MockTwitch;
        }
        else {
            $this->twitch = new \ritero\SDK\TwitchTV\TwitchSDK;
        }
	}

	private function getConfig(string $key): string
	{
	    $sql = "SELECT value FROM config where name=?";
	    $query = $this->db->prepare($sql);
	    $query->execute(array($key));
	    $result = $query->fetch();
	    if($result)
    	    return $result->value;
	    else
	        return "";
	}

	private function setConfig(string $key, string $value)
	{
	    $sql = "UPDATE config SET value=? WHERE name=?";
	    $query = $this->db->prepare($sql);
	    $query->execute(array($value, $key));
	}

    /**
     * @param string $formname
     * @return string
     */
	public function getToken(string $formname): string
	{
	    return generate_token($formname);
    }

    /**
     * @param string $formname
     * @param string $token
     * @return boolean
     */
    public function checkToken(string $formname, string $token): bool
    {
        return validate_token($formname, $token);
    }

    /**
     * @param string $username
     * @param int $type
     * @param string $description = ""
     */
    public function addSubmission(string $username, int $type, $description = "", $channel = null)
    {
        if(!$this->twitchUserExists($username)) {
            throw new Exception("Cannot add a user that doesn't exist on Twitch", 2);
        }
        else if($this->botSubmitted($username)) {
            throw new Exception("Cannot add an already existing bot", 3);
        }
        else if($type == 0) {
            if($description == "")
                throw new Exception("Description can not be empty", 9);
            $type = $description;
        }

        $this->appendToSubmissions($username, $type, 0, $channel);
    }

    private function appendToSubmissions(string $username, $type, $correction = 0, $channel = null)
    {
        if($username == "" || $type == "") {
            throw new Exception("Required fields are empty", 0);
        }
        else if($username == $channel) {
            throw new Exception("Username of the bot and the channel it is in can not match", 7);
        }
        else if($channel !== null && !$this->twitchUserExists($channel)) {
            throw new Exception("Given channel isn't a Twitch channel", 6);
        }
        $sql = "INSERT INTO submissions(name,description,type,channel) VALUES (?,?,?,?)";
        $query = $this->db->prepare($sql);
        $query->execute(array($username, $type, $correction, $channel));
    }

    public function getSubmissions(): array
    {
        $sql = "SELECT name, description, type, date, channel, offline, online, id FROM submissions ORDER BY date DESC";
        $query = $this->db->prepare($sql);
        $query->execute();

        return $query->fetchAll();
    }

    /**
     * @return int
     */
    public function getLastUpdate($table = "bots", $type = 0): int
    {
        $sql = "SELECT date FROM ".$table." ORDER BY date DESC LIMIT 1";
        if($type != 0)
            $sql .= "WHERE type=?";

        $query = $this->db->prepare($sql);
        $query->execute(array($type));

        return strtotime($query->fetch()->date);
    }

    /**
     * @param int $type
     * @return int
     */
    public function getBotCount($type = 0): int
    {
        $sql = "SELECT count FROM count";
        if($type != 0)
            $sql = "SELECT count(name) AS count FROM bots WHERE type=?";

        $query = $this->db->prepare($sql);
        $query->execute(array($type));

        return (int)$query->fetch()->count;
    }

    /**
     * @param string table
     * @return int
     */
    public function getCount(string $table): int
    {
        $sql = "SELECT count(*) AS count FROM ".$table;
        $query = $this->db->prepare($sql);
        $query->execute();

        return (int)$query->fetch()->count;
    }

    /**
     * @param int $count
     * @return int
     */
    public function getPageCount($limit = null, $count = null): int
    {
        $limit = $limit ?? $this->pageSize;
        $count = $count ?? $this->getBotCount();
        if($limit > 0)
            return ceil($count / (float)$limit);
        else
            return 0;
    }

    /**
     * @param int $page
     * @return int
     */
    public function getOffset(int $page): int
    {
        return ($page - 1) * $this->pageSize;
    }

    private function doPagination(PDOStatement $query, $offset = 0, $limit = null, $start = ":start", $stop = ":stop")
    {
        $limit = $limit ?? $this->pageSize;
        $query->bindValue($start, $offset, PDO::PARAM_INT);
        $query->bindValue($stop, $limit, PDO::PARAM_INT);
    }

    public function getBots($page = 1): array
    {
        if($page <= $this->getPageCount($this->pageSize)) {
            $sql = "SELECT * FROM list LIMIT :start,:stop";
            $query = $this->db->prepare($sql);
            $this->doPagination($query, $this->getOffset($page));
            $query->execute();
            return $query->fetchAll();
        }
        else {
            return array();
        }
    }

    public function getAllRawBots($offset = 0, $limit = null): array
    {
        $limit = $limit ?? $this->pageSize;
        if($limit > 0 && $offset < $this->getBotCount()) {
            $sql = "SELECT * FROM bots LIMIT :start,:stop";
            $query = $this->db->prepare($sql);
            $this->doPagination($query, $offset, $limit);
            $query->execute();
            return $query->fetchAll();
        }
        else {
            return array();
        }
    }

    public function getBotsByNames(array $names, $offset = 0, $limit = null): array
    {
        $limit = $limit ?? $this->pageSize;
        $namesCount = count($names);
        if($limit > 0 && $offset < $namesCount) {
            $sql = 'SELECT * FROM bots WHERE name IN ('.implode(',', array_fill(1, $namesCount, '?')).') LIMIT ?,?';
            $query = $this->db->prepare($sql);
            foreach($names as $i => $n) {
                $query->bindValue($i + 1, $n, PDO::PARAM_STR);
            }
            $this->doPagination($query, $offset, $limit, $namesCount + 1, $namesCount + 2);
            $query->execute();

            return $query->fetchAll();
        }
        else {
            return array();
        }
    }

    public function getBotsByType(int $type, $offset = 0, $limit = null): array
    {
        $limit = $limit ?? $this->pageSize;
        //TODO should these bounds checks be in the controller?
        if($limit > 0 && $offset < $this->getBotCount($type)) {
            $sql = "SELECT * FROM bots WHERE type=:type LIMIT :start,:stop";
            $query = $this->db->prepare($sql);
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
        $sql = "SELECT * FROM bots WHERE name=?";
        $query = $this->db->prepare($sql);
        $query->execute(array($name));

        return $query->fetch();
    }

    public function getType(int $id)
    {
        $sql = "SELECT * FROM types WHERE id=?";
        $query = $this->db->prepare($sql);
        $query->bindValue(1, $id, PDO::PARAM_INT);
        $query->execute();

        return $query->fetch();
    }

    public function getAllTypes(): array
    {
        $sql = "SELECT * FROM types ORDER BY name ASC";
        $query = $this->db->prepare($sql);
        $query->execute();

        return $query->fetchAll();
    }

    public function botSubmitted(string $username): bool
    {
        $sql = "SELECT * FROM submissions WHERE name=? AND type=0";
        $query = $this->db->prepare($sql);
        $query->execute(array($username));

        // This is basicly an OR, but the second query gets executed lazily.
        if(!$query->fetch()) {
            $sql = "SELECT * FROM bots WHERE name=?";
            $query_two = $this->db->prepare($sql);
            $query_two->execute(array($username));
            if(!$query_two->fetch())
                return false;
        }
        return true;
    }

    public function removeBot(string $username)
    {
        $sql = "DELETE FROM bots WHERE name=?";
        $query = $this->db->prepare($sql);
        $query->execute(array($username));
    }

    public function removeBots(array $usernames)
    {
        $sql = 'DELETE FROM bots WHERE name IN ('.implode(',', array_fill(1, count($usernames), '?')).')';
        $query = $this->db->prepare($sql);
        $query->execute($usernames);
    }

    public function checkRunning(): bool
    {
        if((int)$this->getConfig('lock') == 1)
            return true;

        $this->setConfig('lock', '1');
        return false;
    }

    public function checkDone()
    {
        $this->setConfig('lock', '0');
    }

    public function getLastCheckOffset(int $step): int
    {
        $lastOffset = (int)$this->getConfig('update_offset');

        $newOffset = ($lastOffset + $step) % $this->getBotCount();

        $this->setConfig('update_offset', $newOffset);

        return $lastOffset;
    }

    public function addCorrection(string $username, int $type, $description = "", $channel = null)
    {
        if(!$this->botSubmitted($username)) {
            throw new Exception("Cannot correct an inexistent bot", 4);
        }
        else if($type == 0) {
            if($description == "")
                throw new Exception("Description can not be empty", 9);
            $type = $description;
        }

        $existingBot = $this->getBot($username);
        if($existingBot->channel == $channel && $existingBot->type == $type) {
            throw new Exception("Metadata must be different", 5);
        }

        $this->appendToSubmissions($username, $type, 1, $channel);
    }

    public function twitchUserExists(string $name): bool
    {
        $channel = $this->twitch->channelGet($name);
        return $this->twitch->http_code != 404;
    }

    public function checkBots(): array
    {
        return $this->checkNBots((int)$this->getConfig('update_size', 10));
    }

    private function checkNBots(int $step): array
    {
        // Make sure we get reserved.
        $this->checkRunning();
        try {
            $offset = $this->getLastCheckOffset($step);
            $bots = $this->getAllRawBots($offset, $step);

            $bots = array_values(array_filter($bots, function($bot) {
                return !$this->twitchUserExists($bot->name);
            }));

            if(count($bots) > 1) {
                $this->removeBots(array_map(function($bot) {
                    return $bot->name;
                }, $bots));
            }
            else if(count($bots) == 1) {
                $this->removeBot($bots[0]->name);
            }
        }
        catch(Exception $e) {
            throw $e;
        }
        finally {
            $this->checkDone();
        }

        return $bots;
    }

    public function getTypes($page = 1): array
    {
        if($page <= $this->getPageCount($this->pageSize)) {
            $sql = "SELECT * FROM typelist LIMIT :start,:stop";
            $query = $this->db->prepare($sql);
            $this->doPagination($query, $this->getOffset($page));
            $query->execute();
            return $query->fetchAll();
        }
        else {
            return array();
        }
    }

    private function getChatters(string $channel): array
    {
        $url = "https://tmi.twitch.tv/group/user/".$channel."/chatters";

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $json = curl_exec($ch);

        if(curl_getinfo($ch, CURLINFO_HTTP_CODE) >= 400) {
            $json = '{"chatters":[]}';
        }

        curl_close($ch);

        return json_decode($json, true)['chatters'];
    }

    private function isInChannel(string $user, string $channel): bool
    {
        $chatters = $this->getChatters($channel);
        $user = strtolower($user);


        foreach($chatters as $category) {
            if(in_array($user, $category))
                return true;
        }
        return false;
    }

    private function setSubmissionInChat(int $id, bool $inChannel, bool $live)
    {
        if($live)
            $sql = "UPDATE submissions SET online=? WHERE id=?";
        else
            $sql = "UPDATE submissions SET offline=? WHERE id=?";

	    $query = $this->db->prepare($sql);
	    $query->execute(array($inChannel, $id));
    }

    public function checkSubmissions(): int
    {
        $submissions = $this->getSubmissions();
        $count = 0;

        foreach($submissions as $submission) {
            if(!empty($submission->channel) && (!isset($submission->online) || !isset($submission->offline))) {
                $stream = $this->twitch->streamGet($submission->channel);
                if(isset($stream->stream) && !isset($submission->online)) {
                    $this->setSubmissionInChat($submission->id, $this->isInChannel($submission->name, $submission->channel), true);
                    ++$count;
                }
                else if(!isset($submission->offline)) {
                    $this->setSubmissionInChat($submission->id, $this->isInChannel($submission->name, $submission->channel), false);
                    ++$count;
                }
            }
        }
        return $count;
    }
}
