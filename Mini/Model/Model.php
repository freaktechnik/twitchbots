<?php

namespace Mini\Model;

use PDO;
use PDOStatement;
use Exception;
use \Mini\Model\TypeCrawler\Storage\StorageFactory;
use \Mini\Model\TypeCrawler\TypeCrawlerController;
use \Mini\Model\PingablePDO;
use GuzzleHttp\{Client, Promise};

/**
 * Submission Exceptions:
 * + is submission only, - is correction only
 *  1: Generic problem
 *+ 2: Cannot add a user that doesn't exist on Twitch
 *+ 3: Cannot add an already existing bot
 *- 4: Cannot correct an inexistent bot
 *- 5: Metadata must be different
 *  6: Given channel isn't a Twitch channel
 *  7: Username of the bot and the channel it is in can not match
 *  8: Required fields are empty
 *  9: Description can not be empty
 * 10:
 *-11: Identical correction already exists
 * 12: Given channel is already a bot
 *+13: Bot cannot be the channel to an existing bot
 */

require __DIR__.'/../../vendor/autoload.php';
include_once 'csrf.php';

class Model
{
    /**
     * The database connection
     * @var PingablePDO
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

    // Database table abstractions
    public $bots;
    public $types;
    public $submissions;
    private $config;

    private $twitchHeaders;

    private static $requestOptions = array('http_errors' => false);


    private $_followsCache;

    public $login;

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
        $options = array(PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION);

        // create new PDO db connection
        $this->db = new PingablePDO($dsn, $config['db_user'], $config['db_pass'], $options);

        $this->pageSize = $config['page_size'];

        $this->bots = new Bots($this->db, $this->pageSize);
        $this->types = new Types($this->db, $this->pageSize);
        $this->config = new Config($this->db);
        $this->submissions = new Submissions($this->db, $this->pageSize);

        $this->twitchHeaders = array_merge(self::$requestOptions, array(
            'headers' => array('Client-ID' => $this->config->get('client-ID'), 'Accept' => 'application/vnd.twitchtv.v3+json')
        ));
        $this->twitchHeadersV5 = array_merge(self::$requestOptions, [
            'headers' => [
                'Client-ID' => $this->config->get('client-ID'),
                'Accept' => 'application/vnd.twitchtv.v5+json'
            ]
        ]);


        $this->client = $client;

        $this->login = new Auth(
            $this->config->get('auth0_clientId'),
            $this->config->get('auth0_clientSecret'),
            $this->config->get('auth0_redirectUrl'),
            $this->config->get('auth0_domain'),
            $this->db
        );
	}

    public function getClientID(): string
    {
        return $this->config->get('client-ID');
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

    public function addSubmission(string $username, int $type, string $description = NULL, string $channel = NULL)
    {
        if(empty($channel)) {
            $channel = null;
        }

        if($type == 0) {
            if(empty($description)) {
                throw new Exception("Description can not be empty", 9);
            }
            $type = $description;
        }

        $this->commonSubmissionChecks($username, $type, $channel);

        if(!$this->twitchUserExists($username)) {
            throw new Exception("Cannot add a user that doesn't exist on Twitch", 2);
        }
        else if($this->hasBot($username)) {
            throw new Exception("Cannot add an already existing bot", 3);
        }
        else if(!empty($this->bots->getBotsByChannel($username))) {
            throw new Exception("Bot cannot be the channel to an existing bot", 13);
        }

        $this->submissions->append($username, $type, Submissions::SUBMISSION, $channel);
    }


    private function commonSubmissionChecks(string $username, $type, string $channel = null)
    {
        if($username == "" || $type == "") {
            throw new Exception("Required fields are empty", 8);
        }
        else if(strtolower($username) == strtolower($channel)) {
            throw new Exception("Username of the bot and the channel it is in can not match", 7);
        }
        else if(!empty($channel) && !empty($this->bots->getBot($channel))) {
            throw new Exception("Given channel is already a bot", 12);
        }
        else if(!empty($channel) && !$this->twitchUserExists($channel, true)) {
            throw new Exception("Given channel isn't a Twitch channel", 6);
        }
    }

    public function hasBot(string $username): bool
    {
        if(!$this->submissions->hasSubmission($username)) {
            if(!$this->bots->getBot($username)) {
                return false;
            }
        }
        return true;
    }

    public function addCorrection(string $username, int $type, string $description = NULL)
    {
        if($type == 0) {
            if(empty($description)) {
                throw new Exception("Description can not be empty", 9);
            }
            else if($this->submissions->hasCorrection($username, $description)) {
                throw new Exception("Identical correction already exists", 11);
            }
            $type = $description;
        }

        $this->commonSubmissionChecks($username, $type);

        $existingBot = $this->bots->getBot($username);
        if(empty($existingBot)) {
            throw new Exception("Cannot correct an inexistent bot", 4);
        }
        else if($existingBot->type == $type) {
            throw new Exception("Metadata must be different", 5);
        }

        $this->submissions->append($username, $type, Submissions::CORRECTION, $existingBot->channel);
    }


    public function checkRunning(): bool
    {
        if((int)$this->config->get('lock') == 1) {
            return true;
        }

        $this->config->set('lock', '1');
        return false;
    }

    public function checkDone()
    {
        $this->db->ping();
        $this->config->set('lock', '0');
    }

    public function twitchUserExists(string $name, $noJustin = false): bool
    {
        $response = $this->client->head("https://api.twitch.tv/kraken/channels/".$name, $this->twitchHeaders);
        $http_code = $response->getStatusCode();
        return $http_code != 404 && (!$noJustin || $http_code != 422);
    }

    public function checkBots(): array
    {
        $botsPerHour = $this->bots->getCount() / (int)$this->config->get('checks_per_day');
        return $this->checkNBots($botsPerHour);
    }

    private function checkBot($bot)
    {
        $this->bots->touchBot($bot->name);

        $exists = $this->twitchUserExists($bot->name);

        if($exists) {
            // Set the twitch IDs in the DB
            $modified = false;
            if(empty($bot->twitch_id)) {
                $bot->twitch_id = $this->getChannelID($bot->name);
                $modified = true;
            }
            if(!empty($bot->channel) && empty($bot->channel_id)) {
                try {
                    $bot->channel_id = $this->getChannelID($bot->channel);
                    $modified = true;
                } catch(Exception $e) {
                    // channel id could not be fetched.
                }
            }
            $this->bots->updateBot($bot);
        }
        return !$exists;
    }

    private function checkNBots(int $step): array
    {
        try {
            $bots = $this->bots->getOldestBots($step);

            //TODO store the Twitch ID of the user for now until we switch to API v5
            // where it'll have to store the username of the bot instead.
            // Will also have to update the channel's username.
            // Will have to update date if any username changes.
            $bots = array_values(array_filter($bots, [$this, 'checkBot']));

            $this->db->ping();
            if(count($bots) > 1) {
                $this->bots->removeBots(array_map(function($bot) {
                    return $bot->name;
                }, $bots));
            }
            else if(count($bots) == 1) {
                $this->bots->removeBot($bots[0]->name);
            }
        }
        catch(Exception $e) {
            throw $e;
        }

        return $bots;
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
            if(in_array($user, $category)) {
                return true;
            }
        }
        return false;
    }

    private function isMod(string $user, array $chatters): bool
    {
        $user = strtolower($user);

        return array_key_exists('moderators', $chatters) && in_array($user, $chatters['moderators']);
    }

    private function isChannelLive(string $channel): bool
    {
        $response = $this->client->get('https://api.twitch.tv/kraken/streams/'.$channel, $this->twitchHeaders);
        $stream = json_decode($response->getBody());
        return isset($stream->stream);
    }

    private function getBio(string $channel)//: ?string
    {
        $response = $this->client->get('https://api.twitch.tv/kraken/users/'.$channel, $this->twitchHeaders);
        $user = json_decode($response->getBody());
        return $user->bio;
    }

    private function hasVODs(string $channel): bool
    {
        $response = $this->client->get('https://api.twitch.tv/kraken/channels/'.$channel.'/videos', $this->twitchHeaders);
        $vods = json_decode($response->getBody());
        return $vods->_total > 0;
    }

    private function getFollowing(string $name): \stdClass
    {
        if(!isset($this->_followsCache)) {
            $response = $this->client->get('https://api.twitch.tv/kraken/users/'.$name.'/follows/channels', $this->twitchHeaders);

            if($response->getStatusCode() >= 400) {
                throw new Exception("Can not get followers for ".$name);
            }

            $this->_followsCache = json_decode($response->getBody());
        }
        return $this->_followsCache;
    }

    private function getFollowingChannel(string $name, string $channel): bool
    {
        $url = 'https://api.twitch.tv/kraken/users/'.$name.'/follows/channels/'.$channel;
        $response = $this->client->head($url, $this->twitchHeaders);

        if($response->getStatusCode() >= 400 && $response->getStatusCode() !== 404) {
            throw new Exception("Can't get following relation");
        }

        return $response->getStatusCode() < 400;
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
        }, $response['channels']))) {
            return true;
        }

        return false;
    }

    private function checkFollowing(\stdClass $submission): bool
    {
        // Update following if needed
        if(!isset($submission->following)) {
            try {
                $follows = $this->getFollowing($submission->name);
            }
            catch(Exception $e) {
                $follows = new \stdClass();
                $follows->_total = 0;
            }
            $this->submissions->setFollowing($submission->id, $follows->_total);
            $submission->following = $follows->_total;
            return true;
        }
        return false;
    }

    private function checkBio(\stdClass $submission): bool
    {
        if(!isset($submission->bio)) {
            try {
                $bio = $this->getBio($submission->name);
            }
            catch(Exception $e) {
                return false;
            }
            $this->submissions->setBio($submission->id, $bio);
            $submission->bio = $bio;
            return true;
        }
        return false;
    }

    private function checkHasVODs(\stdClass $submission): bool
    {
        if(!isset($submission->vods)) {
            try {
                $hasVODs = $this->hasVODs($submission->name);
            }
            catch(Exception $e) {
                return false;
            }

            $this->submissions->setHasVODs($submission->id, $hasVODs);
            $submission->vods = $hasVODs;
            return true;
        }
        return false;
    }

    private function checkFollowingChannel(\stdClass $submission): bool
    {
        // Update following_channel if needed
        if(!isset($submission->following_channel)) {
            if(isset($this->_followsCache)) {
                $follows = $this->_followsCache;
                $follows_channel = $follows->_total > 0 && in_array($submission->channel, array_map(function($chan) {
                    return strtolower($chan->channel->name);
                }, $follows->follows));
                if(!$follows_channel && $follows->_total > count($follows->follows)) {
                    unset($follows_channel);
                }
            }
            if(!isset($follows_channel)) {
                try {
                    $follows_channel = $this->getFollowingChannel($submission->name, $submission->channel);
                }
                catch(Exception $e) {
                    return false;
                }
            }

            $this->submissions->setFollowingChannel($submission->id, $follows_channel);
            $submission->following_channel = $follows_channel;
            return true;
        }
        return false;
    }

    public function checkSubmissions(): int
    {
        $submissions = $this->submissions->getSubmissions();
        $count = 0;

        foreach($submissions as $submission) {
            $this->db->ping();
            $didSomething = false;

            if($this->checkFollowing($submission)) {
                $didSomething = true;
            }
            if($this->checkBio($submission) && !$didSomething) {
                $didSomething = true;
            }
            if($this->checkHasVODs($submission) && !$didSomething) {
                $didSomething = true;
            }

            if(!empty($submission->channel)) {
                if($this->checkFollowingChannel($submission) && !$didSomething) {
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

                            if($isInChannel && !$submission->ismod) {
                                $isMod = $this->isMod($submission->name, $chatters);
                            }
                            else if(!$ranModCheck) {
                                $isMod = $this->getModStatus($submission->name, $submission->channel);
                            }
                        }
                        catch(Exception $e) {
                            $isInChannel = NULL;
                        }

                        $this->submissions->setInChat($submission->id, $isInChannel, $live);
                        if($live) {
                            $submission->online = $isInChannel;
                        }
                        else {
                            $submission->offline = $isInChannel;
                        }

                        if($isMod !== null) {
                            $this->submissions->setModded($submission->id, $isMod);
                            $submission->ismod = $isMod;
                            $ranModCheck = true;
                        }

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
                    if($isMod !== null) {
                        $this->submissions->setModded($submission->id, $isMod);
                        $submission->isMod = $isMod;
                    }
                    $didSomething = true;
                }
            }

            if($didSomething) {
                ++$count;
            }
            unset($this->_followsCache);
        }

        return $count;
    }

    public function typeCrawl(): int
    {
        $storage = new StorageFactory('PDOStorage', array($this->db, 'config'));
        $controller = new TypeCrawlerController($storage);

        $foundBots = $controller->triggerCrawl();

        $this->db->ping();

        $count = 0;
        $max = count($foundBots);
        for($i = 0; $i < $max; $i += 1) {
            $bot = $foundBots[$i];
            if(empty($this->bots->getBot($bot->name)) && $this->twitchUserExists($bot->name) && (empty($bot->channel) || $this->twitchUserExists($bot->channel, true))) {
                $this->bots->addBot($bot->name, $bot->type, $bot->channel);
                $count += 1;

                // Remove any matching submissions.
                $this->submissions->removeSubmissions($bot->name, (string)$bot->type);
            }
        }

        return $count;
    }

    /**
     * Approves a submission. Currently only supports submissions and ones with
     * a numeric description.
     */
    public function approveSubmission(int $id): bool
    {
        $submission = $this->submissions->getSubmission($id);

        if(!is_numeric($submission->description) || $submission->type != 0) {
            return false;
        }

        $twitchId = $this->getChannelID($submission->name);

        $channelId = null;
        if(!empty($submission->channel)) {
            $channelId = $this->getChannelID($submission->channel);
        }

        $this->bots->addBot($twitchId, $submission->name, (int)$submission->description, $submission->channel, $channelId);
        //TODO actually remove submissions by name if type==0, don't do that if type==1
        $this->submissions->removeSubmission($id);

        return true;
    }

    private function getChannelID(string $username): int
    {
        $url = 'https://api.twitch.tv/kraken/users/?login='.$username;
        $response = $this->client->get($url, $this->twitchHeadersV5);

        if($response->getStatusCode() >= 400 && $response->getStatusCode() !== 404) {
            throw new Exception("User could not be found");
        }

        return json_decode($response->getBody(), true)['_id'];
    }
}
