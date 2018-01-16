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

    /**
     * @var Bots
     */
    public $bots;
    /**
     * @var Types
     */
    public $types;
    /**
     * @var Submissions
     */
    public $submissions;
    /**
     * @var Config
     */
    private $config;
    /**
     * @var ConfirmedPeople
     */
    private $confirmedPeople;

    private $twitchHeaders;
    private $twitchHeadersV5;

    private $venticHeaders;

    private static $requestOptions = ['http_errors' => false];


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
        $this->confirmedPeople = new ConfirmedPeople($this->db);

        $this->twitchHeaders = array_merge(self::$requestOptions, array(
            'headers' => array('Client-ID' => $this->config->get('client-ID'), 'Accept' => 'application/vnd.twitchtv.v3+json')
        ));
        $this->twitchHeadersV5 = array_merge(self::$requestOptions, [
            'headers' => [
                'Client-ID' => $this->config->get('client-ID'),
                'Accept' => 'application/vnd.twitchtv.v5+json'
            ]
        ]);
        $this->venticHeaders = array_merge(self::$requestOptions, [
            'headers' => [
                'User-Agent' => $this->config->get('3v-ua')
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

        $channelId = $this->commonSubmissionChecks($username, $type, $channel);

        try {
            $id = $this->getChannelID($username);
        } catch(Exception $e) {
            throw new Exception("Cannot add a user that doesn't exist on Twitch", 2);
        }
        if($this->hasBot($id)) {
            throw new Exception("Cannot add an already existing bot", 3);
        }
        else if(!empty($this->bots->getBotsByChannel($username))) {
            throw new Exception("Bot cannot be the channel to an existing bot", 13);
        }
        else if($this->confirmedPeople->has($id)) {
            throw new Exception("Cannot add this user", 14);
        }

        $this->submissions->append($id, $username, $type, Submissions::SUBMISSION, $channel, $channelId);
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

        $channelId = NULL;
        if(!empty($channel)) {
            try {
                $channelId = $this->getChannelID($channel);
            } catch(Exception $e) {
                throw new Exception("Given channel isn't a Twitch channel", 6);
            }
        }
        return $channelId;
    }

    public function hasBot(string $id): bool
    {
        if(!$this->submissions->hasSubmission($id)) {
            if(!$this->bots->getBotByID($id)) {
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
        else if($type == 0 && $this->submissions->hasCorrection($existingBot->twitch_id, $description)) {
            throw new Exception("Identical correction already exists", 11);
        }

        $this->submissions->append($existingBot->twitch_id, $username, $type, Submissions::CORRECTION, $existingBot->channel, $existingBot->channel_id);
    }

    public function twitchUserExists(string $id, $noJustin = false): bool
    {
        $response = $this->client->head("https://api.twitch.tv/kraken/users/".$id, $this->twitchHeadersV5);
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
        $this->bots->touchBot($bot->twitch_id);

        $exists = $this->twitchUserExists($bot->twitch_id);

        if($exists) {
            // Set the twitch IDs in the DB
            $modified = false;

            $apiUsername = $this->getChannelName($bot->twitch_id);
            if($apiUsername != $bot->name) {
                $bot->name = $apiUsername;
                $modified = true;
            }

            if(!empty($bot->channel_id)) {
                $channelUsername = $this->getChannelName($bot->channel_id);
                if($channelUsername != $bot->channel) {
                    $bot->channel = $channelUsername;
                    $modified = true;
                }
            }
            if($modified) {
                $this->bots->updateBot($bot);
            }
        }
        return !$exists;
    }

    private function checkNBots(int $step): array
    {
        try {
            $bots = $this->bots->getOldestBots($step);

            $bots = array_values(array_filter($bots, [$this, 'checkBot']));

            $this->db->ping();
            if(count($bots) > 1) {
                $this->bots->removeBots(array_column($bots, 'twitch_id'));
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

    private function isChannelLive(string $channelId): bool
    {
        $response = $this->client->get('https://api.twitch.tv/kraken/streams/'.$channelId, $this->twitchHeadersV5);
        $stream = json_decode($response->getBody());
        return isset($stream->stream);
    }

    private function getBio(string $channelId)//: ?string
    {
        $response = $this->client->get('https://api.twitch.tv/kraken/users/'.$channelId, $this->twitchHeadersV5);
        $user = json_decode($response->getBody());
        if(isset($user->bio)) {
            return $user->bio;
        } else {
            return NULL;
        }
    }

    private function hasVODs(string $channelId): bool
    {
        $response = $this->client->get('https://api.twitch.tv/kraken/channels/'.$channelId.'/videos?broadcast_type=archive,highlight,upload', $this->twitchHeadersV5);
        $vods = json_decode($response->getBody());
        return $vods->_total > 0;
    }

    private function getFollowing(string $id): \stdClass
    {
        if(!isset($this->_followsCache)) {
            $response = $this->client->get('https://api.twitch.tv/kraken/users/'.$id.'/follows/channels', $this->twitchHeadersV5);

            if($response->getStatusCode() >= 400) {
                throw new Exception("Can not get followers for ".$name);
            }

            $this->_followsCache = json_decode($response->getBody());
        }
        return $this->_followsCache;
    }

    private function getFollowingChannel(string $id, string $channelId): bool
    {
        $url = 'https://api.twitch.tv/kraken/users/'.$id.'/follows/channels/'.$channelId;
        $response = $this->client->head($url, $this->twitchHeadersV5);

        if($response->getStatusCode() >= 400 && $response->getStatusCode() !== 404) {
            throw new Exception("Can't get following relation");
        }

        return $response->getStatusCode() < 400;
    }

    private function getModStatus(string $username, string $channel, int $page = 0): bool
    {
        $pageSize = 100;
        $url = "https://twitchstuff.3v.fi/modlookup/api/user/" . $username . "?limit=" . $pageSize . "&offset=" . $page * $pageSize;

        $response = $this->client->get($url, array(), $this->venticHeaders);

        if($response->getStatusCode() >= 400) {
            throw new Exception("Could not get mod status");
        }

        $response = json_decode($response->getBody(), true);

        if($response['count'] > 0 && in_array(strtolower($channel), array_column($response['channels'], 'name'))) {
            return true;
        }

        if($response['count'] > ($page + 1) * $pageSize) {
            return $this->getModStatus($username, $channel, $page + 1);
        }
        return false;
    }

    private function getBotVerified(string $id): bool {
        $url = "https://api.twitch.tv/kraken/users/".$id."/chat";
        $response = $this->client->get($url, $this->twitchHeadersV5);

        if($response->getStatusCode() >= 400) {
            throw new Exception("Could not get verified status");
        }

        $json = json_decode($response->getBody(), true);

        if($json['is_known_bot']) {
            return true;
        }
        // Legacy, so handle its presence gracefully
        else if(isset($json['is_verified_bot'])) {
            return $json['is_verified_bot'];
        }
        return false;
    }

    private function getBTTVBots(string $channel): array {
        $url = "https://api.betterttv.net/2/channels/".$channel;
        $response = $this->client->get($url);

        if($response->getStatusCode() >= 400) {
            throw new Error("Could not get BTTV bots");
        }

        $json = json_decode($response->getBody(), true);

        return $json['bots'];
    }

    private function checkFollowing(\stdClass $submission): bool
    {
        // Update following if needed
        if(!isset($submission->following)) {
            try {
                $follows = $this->getFollowing($submission->twitch_id);
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
                $bio = $this->getBio($submission->twitch_id);
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
                $hasVODs = $this->hasVODs($submission->twitch_id);
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
                $follows_channel = $follows->_total > 0 && in_array($submission->channel_id, array_map(function($chan) {
                    return strtolower($chan->channel->_id);
                }, $follows->follows));
                if(!$follows_channel && $follows->_total > count($follows->follows)) {
                    unset($follows_channel);
                }
            }
            if(!isset($follows_channel)) {
                try {
                    $follows_channel = $this->getFollowingChannel($submission->twitch_id, $submission->channel_id);
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

    private function checkVerified(\stdClass $submission): bool {
        if(!isset($submission->verified) || !$submission->verified) {
            try {
                $verified = $this->getBotVerified($submission->twitch_id);
            }
            catch(Exception $e) {
                return false;
            }

            $this->submissions->setVerified($submission->id, $verified);
            $submission->verified = $verified;
            return true;
        }
        return false;
    }

    public function checkBTTVBot(\stdClass $submission): bool {
        if(isset($submission->channel)) {
            try {
                $bttvBots = $this->getBTTVBots($submission->channel);
            }
            catch(Exception $e) {
                return false;
            }
            //TODO maybe be a little more cautious with this and set a different flag.
            if(in_array($submission->name, $bttvBots)) {
                $this->submissions->setVerified($submission->id, true);
                $submission->verified = true;
                return true;
            }
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

            if(empty($submission->twitch_id)) {
                try {
                    $submission->twitch_id = $this->getChannelID($submission->name);
                    $this->submissions->setTwitchID($submission->id, $submission->twitch_id);
                    $didSomething = true;
                }
                catch(Exception $e) {
                    if($e->getCode() == 404 || $e->getCode() == 422) {
                        $this->submissions->removeSubmission($submission->id);
                        continue;
                    }
                }
            }
            else {
                try {
                    $apiName = $this->getChannelName($submission->twitch_id);
                }
                catch(Exception $e) {
                    if($e->getCode() == 404 || $e->getCode() == 422) {
                        $this->submissions->removeSubmission($submission->id);
                        continue;
                    }
                }
                if($apiName != $submission->name) {
                    $submission->name = $apiName;
                    $this->submissions->updateName($submission->id, $submission->name);
                    $didSomething = true;
                }
            }

            if($submission->type == 0 && $this->checkVerified($submission) && !$didSomething) {
                $didSomething = true;
            }
            if($submission->type == 0 && $this->checkBTTVBot($submission) && !$didSomething) {
                $didSomething = true;
            }
            if($this->checkFollowing($submission) && !$didSomething) {
                $didSomething = true;
            }
            if($this->checkBio($submission) && !$didSomething) {
                $didSomething = true;
            }
            if($this->checkHasVODs($submission) && !$didSomething) {
                $didSomething = true;
            }

            if(!empty($submission->channel)) {
                if(empty($submission->channel_id)) {
                    try {
                        $submission->channel_id = $this->getChannelID($submission->channel);
                        $this->submissions->setTwitchID($submission->id, $submission->channel_id, "channel");
                        $didSomething = true;
                    }
                    catch(Exception $e) {
                        if($e->getCode() == 404 || $e->getCode() == 422) {
                            $this->submissions->clearChannel($submission->id);
                        }
                    }
                }
                else {
                    try {
                        $apiName = $this->getChannelName($submission->channel_id);
                    }
                    catch(Exception $e) {
                        if($e->getCode() == 404 || $e->getCode() == 422) {
                            $this->submissions->clearChannel($submission->id);
                        }
                    }
                    if($apiName != $submission->channel) {
                        $submission->name = $apiName;
                        $this->submissions->updateChannelName($submission->id, $submission->name);
                        $didSomething = true;
                    }
                }

                if($this->checkFollowingChannel($submission) && !$didSomething) {
                    $didSomething = true;
                }

                $ranModCheck = isset($submission->ismod);
                // Update online or offline and mod if needed
                if(!$submission->online || !isset($submission->offline)) {
                    $live = $this->isChannelLive($submission->channel_id);
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
                if($submission->verified && $submission->type == 0) {
                    $this->approveSubmission($submission->id);
                }
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
            try {
                $twitchId = $this->getChannelID($bot->name);
                if(empty($this->bots->getBotByID($twitchId))) {
                    $channelId = NULL;
                    if(!empty($bot->channel)) {
                        $channelId = $this->getChannelID($bot->channel);
                    }
                    $this->bots->addBot($twitchId, $bot->name, $bot->type, $bot->channel, $channelId);
                    $count += 1;

                    // Remove any matching submissions.
                    $this->submissions->removeSubmissions($bot->name);
                }
            } catch(Exception $e) {
                //TODO log error?
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

        if($submission->type != 0) {
            return false;
        }

        if(!empty($submission->twitch_id)) {
            $twitchId = $submission->twitch_id;
        }
        else {
            $twitchId = $this->getChannelID($submission->name);
        }

        $channelId = null;
        if(!empty($submission->channel)) {
            if(!empty($submission->channel_id)) {
                $channelId = $submission->channel_id;
            }
            else {
                $channelId = $this->getChannelID($submission->channel);
            }
        }

        if(is_numeric($submission->description)) {
            $this->bots->addBot($twitchId, $submission->name, (int)$submission->description, $submission->channel, $channelId);
        }
        else if(!$submission->verified){
            //TODO add ui for editor to assign a type/create a type, since this just discards the description.
            $this->bots->addBot($twitchId, $submission->name, null, $submission->channel, $channelId);
        }
        //TODO actually remove submissions by twitch_id if type==0, don't do that if type==1
        $this->submissions->removeSubmission($id);

        return true;
    }

    private function getChannelID(string $username): string
    {
        $url = 'https://api.twitch.tv/kraken/users/?login='.$username;
        $response = $this->client->get($url, $this->twitchHeadersV5);

        if($response->getStatusCode() >= 400 && $response->getStatusCode() !== 404) {
            throw new Exception("User could not be found");
        }

        $users = json_decode($response->getBody(), true)['users'];
        if(count($users) > 0){
            return $users[0]['_id'];
        }
        else {
            throw new Exception("User could not be found");
        }
    }

    private function getChannelName(string $id): string
    {
        $url = 'https://api.twitch.tv/kraken/users/' . $id;
        $response = $this->client->get($url, $this->twitchHeadersV5);

        if($response->getStatusCode() >= 400) {
            throw new Exception("Could not get username for ".$id, $response->getStatusCode());
        }

        $user = json_decode($response->getBody(), true);
        return $user['name'];
    }

    public function updateSubmission(int $id, string $description, string $channel)
    {
        $submission = $this->submissions->getSubmission($id);

        if($submission->description != $description) {
            $this->submissions->updateDescription($id, $description);
        }

        if($submission->channel != $channel) {
            $this->submissions->clearChannel($id);
            if(!empty($channel)) {
                $this->submissions->updateChannelName($id, $channel);
            }
        }
    }
}
