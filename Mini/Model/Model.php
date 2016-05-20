<?php

namespace Mini\Model;

use PDO;
use PDOStatement;
use Exception;
use \Mini\Model\TypeCrawler\Storage\StorageFactory;
use \Mini\Model\TypeCrawler\TypeCrawlerController;
use GuzzleHttp\{Client, Promise};

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
	 * The guzzle client
	 * @var Client
	 */
	private $client;

	/**
	 * The default page size
	 * @var int
	 */
	private $pageSize;

    private $twitchHeaders;

    private static $requestOptions = array('http_errors' => false);

    /**
     * When creating the model, the configs for database connection creation are needed
     * @param $config
     */
    function __construct(array $config, Client $client)
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

        $this->twitchHeaders = array_merge(self::$requestOptions, array(
            'headers' => array('Client-ID' => $this->getConfig('client-ID'), 'Accept' => 'application/vnd.twitchtv.v3+json')
        ));
        $this->client = $client;
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
     * @param string $channel = null
     */
    public function addSubmission(string $username, int $type, $description = "", $channel = null)
    {
        if(empty($channel))
            $channel = null;

        if($type == 0) {
            if($description == "")
                throw new Exception("Description can not be empty", 9);
            $type = $description;
        }

        $this->commonSubmissionChecks($username, $type, $channel);

        if(!$this->twitchUserExists($username)) {
            throw new Exception("Cannot add a user that doesn't exist on Twitch", 2);
        }
        else if($this->botSubmitted($username)) {
            throw new Exception("Cannot add an already existing bot", 3);
        }
        else if(!empty($this->getBotsByChannel($username))) {
            throw new Exception("Bot cannot be the channel to an existing bot", 13);
        }

        $this->appendToSubmissions($username, $type, 0, $channel);
    }

    private function appendToSubmissions(string $username, $type, $correction = 0, $channel = null)
    {
        $sql = "INSERT INTO submissions(name,description,type,channel) VALUES (?,?,?,?)";
        $query = $this->db->prepare($sql);
        $params = array($username, $type, $correction, $channel);

        $query->execute($params);
    }

    private function commonSubmissionChecks(string $username, $type, $channel = null)
    {
        if($username == "" || $type == "") {
            throw new Exception("Required fields are empty", 0);
        }
        else if(strtolower($username) == strtolower($channel)) {
            throw new Exception("Username of the bot and the channel it is in can not match", 7);
        }
        else if(!empty($channel) && !empty($this->getBot($channel))) {
            throw new Exception("Given channel is already a bot", 12);
        }
        else if(!empty($channel) && !$this->twitchUserExists($channel, true)) {
            throw new Exception("Given channel isn't a Twitch channel", 6);
        }
    }

    public function getSubmissions(): array
    {
        $sql = "SELECT name, description, type, date, channel, offline, online, ismod, following, following_channel, id FROM submissions ORDER BY date DESC";
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
            if(!$this->getBot($username))
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

    private function hasCorrection(string $username, string $description, $channel = null): bool
    {
        $sql = 'SELECT * FROM submissions WHERE type=1 AND name=? AND  description=? AND channel=?';
        $query = $this->db->prepare($sql);
        $query->execute(array($username, $description, $channel));
        return $query->fetch() != null;
    }

    public function addCorrection(string $username, int $type, $description = "", $channel = null)
    {
        if(empty($channel))
            $channel = null;

        if($type == 0) {
            if($description == "")
                throw new Exception("Description can not be empty", 9);
            else if($this->hasCorrection($username, $description, $channel))
                throw new Exception("Identical correction already exists", 11);
            $type = $description;
        }

        $this->commonSubmissionChecks($username, $type, $channel);

        $existingBot = $this->getBot($username);
        if(empty($existingBot)) {
            throw new Exception("Cannot correct an inexistent bot", 4);
        }
        else if($existingBot->channel == $channel && $existingBot->type == $type) {
            throw new Exception("Metadata must be different", 5);
        }
        else if($type != 0 && $type == $existingBot->type && isset($channel)) {
            $type = $this->getType($type);
            if($type->multichannel) {
                throw new Exception("Example channel set for a multichannel bot", 10);
            }
        }

        $this->appendToSubmissions($username, $type, 1, $channel);
    }

    public function twitchUserExists(string $name, $noJustin = false): bool
    {
        $response = $this->client->head("https://api.twitch.tv/kraken/channels/".$name, $this->twitchHeaders);
        $http_code = $response->getStatusCode();
        return $http_code != 404 && (!$noJustin || $http_code != 422);
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
        $response = $this->client->get("https://tmi.twitch.tv/group/user/".$channel."/chatters", array(), self::$requestOptions);
        if($response->getStatusCode() >= 400) {
            throw new Exception("No chatters returned");
        }

        return json_decode($response->getBody(), true)['chatters'];
    }

    private function isInChannel(string $user, array $chatters): bool
    {
        $user = strtolower($user);


        foreach($chatters as $category) {
            if(in_array($user, $category))
                return true;
        }
        return false;
    }

    private function isMod(string $user, array $chatters): bool
    {
        $user = strtolower($user);

        return array_key_exists('moderators', $chatters) && in_array($user, $chatters['moderators']);
    }

    private function setSubmissionInChat(int $id, $inChannel, bool $live)
    {
        if($live)
            $sql = "UPDATE submissions SET online=? WHERE id=?";
        else
            $sql = "UPDATE submissions SET offline=? WHERE id=?";

	    $query = $this->db->prepare($sql);
	    $query->execute(array($inChannel, $id));
    }

    private function setSubmissionModded(int $id, $isMod)
    {
        $sql = "UPDATE submissions SET ismod=? WHERE id=?";
        $query = $this->db->prepare($sql);
        $query->execute(array($isMod, $id));
    }

    private function setSubmissionFollowing(int $id, $followingCount = null)
    {
        $sql = "UPDATE submissions SET following=? WHERE id=?";
        $query = $this->db->prepare($sql);
        $query->execute(array($followingCount, $id));
    }

    private function setSubmissionFollowingChannel(int $id, $followingChannel = null)
    {
        $sql = "UPDATE submissions SET following_channel=? WHERE id=?";
        $query = $this->db->prepare($sql);
        $query->execute(array($followingChannel, $id));
    }

    private function isChannelLive(string $channel): bool
    {
        $response = $this->client->get('https://api.twitch.tv/kraken/streams/'.$channel, $this->twitchHeaders);
        $stream = json_decode($response->getBody());
        return isset($stream->stream);
    }

    public function checkSubmissions(): int
    {
        $submissions = $this->getSubmissions();
        $count = 0;

        foreach($submissions as $submission) {
            $didSomething = false;

            // Update following if needed
            if(!isset($submission->following)) {
                try {
                    $follows = $this->getFollowing($submission->name);
                }
                catch(Exception $e) {
                    $follows = new \stdClass();
                    $follows->_total = 0;
                }
                $this->setSubmissionFollowing($submission->id, $follows->_total);
                $didSomething = true;
            }

            if(!empty($submission->channel)) {
                // Update following_channel if needed
                if(!isset($submission->following_channel)) {
                    $follows_channel = null;
                    if(isset($follows)) {
                        $follows_channel = $follows->_total > 0 && in_array($submission->channel, array_map(function($chan) {
                            return strtolower($chan->channel->name);
                        }, $follows->follows));
                    }
                    else {
                        try {
                            $follows_channel = $this->getFollowingChannel($submission->name, $submission->channel);
                        }
                        catch(Exception $e) {
                            $follows_channel = null;
                        }
                    }

                    $this->setSubmissionFollowingChannel($submission->id, $follows_channel);
                    $didSomething = true;
                }

                $ranModCheck = isset($submission->ismod);
                // Update online or offline and mod if needed
                if(!$submission->online || !isset($submission->offline)) {
                    $live = $this->isChannelLive($submission->channel);
                    if(($live && !$submission->online) || (!$live && !isset($submission->offline))) {
                        $isMod = null;
                        try {
                            $chatters = $this->getChatters($submission->channel);
                            $isInChannel = $this->isInChannel($submission->name, $chatters);

                            if($isInChannel && !$submission->ismod)
                                $isMod = $this->isMod($submission->name, $chatters);
                            else if(!$ranModCheck)
                                $isMod = $this->getModStatus($submission->name, $submission->channel);
                        }
                        catch(Exception $e) {
                            $isInChannel = null;
                        }

                        $this->setSubmissionInChat($submission->id, $isInChannel, $live);
                        if($isMod !== null)
                            $this->setSubmissionModded($submission->id, $isMod);

                        $ranModCheck = true;
                        $didSomething = true;
                    }
                }

                // If user wasn't in channel chat and mod not set, get mdo status
                if(!$ranModCheck) {
                    try {
                        $isMod = $this->getModStatus($submission->name, $submission->channel);
                    }
                    catch(Exception $e) {
                        $isMod = null;
                    }
                    if($isMod !== null)
                        $this->setSubmissionModded($submission->id, $isMod);

                    $didSomething = true;
                }
            }

            if($didSomething)
                ++$count;
        }

        return $count;
    }

    private function getModStatus(string $username, string $channel): bool
    {
        $url = "https://twitchstuff.3v.fi/api/mods/" . $username;

        $response = $this->client->get($url, array(), self::$requestOptions);

        if($response->getStatusCode() >= 400) {
            throw new Exception("Could not get mod status");
        }

        $response = json_decode($response->getBody(), true);

        if($response['count'] > 0 && in_array(strtolower($channel), array_map(function($i) {
            return $i['name'];
        }, $response['channels'])))
            return true;

        return false;
    }

    public function canCheck(string $token): bool
    {
        if(strlen($token) == 0 || !preg_match('/^[A-Za-z0-9]+$/', $token)) {
            throw new Exception('Invalid token');
        }

        $sql = "SELECT * FROM check_tokens WHERE token=?";
        $query = $this->db->prepare($sql);
        $query->execute(array($token));
        $results = $query->fetchAll();
        return count($results) > 0 && in_array($token, array_map(function($result) {
            return $result->token;
        }, $results), true);
    }

    private function getFollowing(string $name)
    {
        $response = $this->client->get('https://api.twitch.tv/kraken/users/'.$name.'/follows/channels', $this->twitchHeaders);

        if($response->getStatusCode() >= 400)
            throw new Exception("Can not get followers for ".$name);

        $following = json_decode($response->getBody());
        return $following;
    }

    private function getFollowingChannel(string $name, string $channel): bool
    {
        $url = 'https://api.twitch.tv/kraken/users/'.$name.'/follows/channels/'.$channel;
        $response = $this->client->head($url, $this->twitchHeaders);

        if($response->getStatusCode() >= 400 && $response->getStatusCode() !== 404)
            throw new Exception("Can't get following relation");

        return $response->getStatusCode() < 400;
    }

    private function addBot(string $name, int $type, $channel = null)
    {
        $sql = "INSERT INTO bots (name,type,channel) VALUES (?,?,?)";
        $query = $this->db->prepare($sql);
        $query->bindValue(1, strtolower($name), PDO::PARAM_STR);
        $query->bindValue(2, $type, PDO::PARAM_INT);
        $query->bindValue(3, strtolower($channel), PDO::PARAM_STR);
        $query->execute();
    }

    public function typeCrawl(): int
    {
        $storage = new StorageFactory('PDOStorage', array($this->db, 'config'));
        $controller = new TypeCrawlerController($storage);

        $foundBots = $controller->triggerCrawl();

        $count = 0;
        foreach($foundBots as $bot) {
            if(empty($this->getBot($bot->name)) && $this->twitchUserExists($bot->name) && (empty($bot->channel) || $this->twitchUserExists($bot->channel, true))) {
                $this->addBot($bot->name, $bot->type, $bot->channel);
                $count += 1;
                //TODO remove any submission of a bot with this name and type
            }
        }

        return $count;
    }

    private function getBotsByChannel(string $channel): array
    {
        $sql = "SELECT * FROM bots WHERE channel=?";
        $query = $this->db->prepare($sql);
        $query->execute(array($channel));

        return $query->fetchAll();
    }
}
