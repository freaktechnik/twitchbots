<?php

namespace Mini\Model;

use PDO;
use Exception;
use \Mini\Model\TypeCrawler\Storage\StorageFactory;
use \Mini\Model\TypeCrawler\TypeCrawlerController;
use GuzzleHttp\Client;

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
    private const SWORD_PAGESIZE = 500;
    /**
     * The database connection
     * @var PingablePDO $db
     */
    private $db;

    /**
     * The guzzle client
     * @var Client $client
     */
    private $client;

    /**
     * The default page size
     * @var int $pageSize
     */
    private $pageSize;

    /**
     * @var Bots $bots
     */
    public $bots;
    /**
     * @var Types $types
     */
    public $types;
    /**
     * @var Submissions $submissions
     */
    public $submissions;
    /**
     * @var Config $config
     */
    private $config;
    /**
     * @var ConfirmedPeople $confirmedPeople
     */
    private $confirmedPeople;
    /**
     * @var Twitch $twitch
     */
    private $twitch;

    private $venticHeaders = [];

    private static $requestOptions = ['http_errors' => false];

    /**
     * @var Auth $login
     */
    public $login;

    /**
     * When creating the model, the configs for database connection creation are needed
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
        $this->twitch = new Twitch($client, $this->config->get('client-ID'), self::$requestOptions);

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

    public function getToken(string $formname): string
    {
        return generate_token($formname);
    }

    public function checkToken(string $formname, string $token): bool
    {
        return validate_token($formname, $token);
    }

    public function addSubmission(string $username, int $type, string $description = NULL, string $channel = NULL): void
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
            $id = $this->twitch->getChannelID($username);
        }
        catch(Exception $e) {
            throw new Exception("Cannot add a user that doesn't exist on Twitch", 2);
        }
        if($this->hasBot($id)) {
            throw new Exception("Cannot add an already existing bot", 3);
        }
        else if(!empty($this->bots->getBotsByChannelID($id))) {
            throw new Exception("Bot cannot be the channel to an existing bot", 13);
        }
        else if($this->confirmedPeople->has($id)) {
            throw new Exception("Cannot add this user", 14);
        }

        $this->submissions->append($id, $username, $type, Submissions::SUBMISSION, $channel, $channelId);
    }

    /**
     * @return string|null
     */
    private function commonSubmissionChecks(string $username, $type, ?string $channel = null): ?string
    {
        $channelId = null;
        if($username == "" || $type == "") {
            throw new Exception("Required fields are empty", 8);
        }
        else if(!empty($channel)) {
            if(strtolower($username) == strtolower($channel)) {
                throw new Exception("Username of the bot and the channel it is in can not match", 7);
            }
            else if(!empty($this->bots->getBot($channel))) {
                throw new Exception("Given channel is already a bot", 12);
            }

            try {
                $channelId = $this->twitch->getChannelID($channel);
            }
            catch(Exception $e) {
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

    public function addCorrection(string $username, int $type, ?string $description = null): void
    {
        if($type == 0) {
            if(empty($description)) {
                throw new Exception("Description can not be empty", 9);
            }
            $type = $description;
        }

        $this->commonSubmissionChecks($username, $type);

        try {
            $existingBot = $this->bots->getBotOrThrow($username);
        }
        catch(Exception $e)
        {
            throw new Exception("Cannot correct an inexistent bot", 4);
        }
        /** @var Bot $existingBot */
        if($existingBot->type == $type) {
            throw new Exception("Metadata must be different", 5);
        }
        else if($type == 0 && $this->submissions->hasCorrection($existingBot->twitch_id, $description)) {
            throw new Exception("Identical correction already exists", 11);
        }

        $this->submissions->append($existingBot->twitch_id, $username, $type, Submissions::CORRECTION, $existingBot->channel, $existingBot->channel_id);
    }

    /**
     * @return string[]
     */
    public function checkBots(): array
    {
        //TODO bulk-requests for this stuff.
        $botsPerHour = $this->bots->getCount() / (int)$this->config->get('checks_per_day');
        return $this->checkNBots($botsPerHour);
    }

    /**
     * @return string[]
     */
    private function checkNBots(int $step): array
    {
        $bots = $this->bots->getOldestBots($step);

        $sliceSize = 100;
        $i = 0;
        $botsToRemove = [];
        while(count($bots) > 0) {
            $idsToRequest = [];
            while(count($idsToRequest) < $sliceSize) {
                $bot = $bots[$i];
                $this->bots->touchBot($bot->twitch_id);
                $idsToRequest[$bot->twitch_id] = $i;
                if($bot->channel_id && count($idsToRequest) < $sliceSize) {
                    $idsToRequest[$bot->channel_id] = $i;
                }
                ++$i;
            }
            $users = $this->twitch->getChannelInfo(array_keys($idsToRequest));
            foreach($users as $user) {
                $j = $idsToRequest[$user->id];
                $bot = $bots[$j];
                $updated = false;
                if($bot->twitch_id == $user->id && $bot->name != $user->login) {
                    $bot->name = $user->login;
                    $updated = true;
                }
                else if($bot->channel_id == $user->id && $bot->channel != $user->login) {
                    $bot->channel = $user->login;
                    $modified = true;
                }
                if($modified) {
                    $this->bots->updateBot($bot);
                }
                unset($idsToRequest[$user->id]);
            }
            foreach($idsToRequest as $id => $index) {
                $bot = $bots[$index];
                if($bot->twitch_id == $id) {
                    $botsToRemove[] = $id;
                }
                else if($bot->channel_id == $id && !in_array($bot->twitch_id, $botsToRemove)) {
                    $bot->channel_id = null;
                    $bot->channel = null;
                    $this->bots->updateBot($bot);
                }
            }
        }

        $this->db->ping();
        if(count($botsToRemove) > 0) {
            $this->bots->removeBots($botsToRemove);
        }

        return $botsToRemove;
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

    private function getSwords(string $username, int $page = 0, int $pageSize = self::SWORD_PAGESIZE): array
    {
        $url = "https://twitchstuff.3v.fi/modlookup/api/user/" . $username . "?limit=" . $pageSize . "&offset=" . $page * $pageSize;

        $response = $this->client->get($url, $this->venticHeaders);

        if($response->getStatusCode() >= 400) {
            throw new Exception("Could not get mod status");
        }

        $response = json_decode($response->getBody(), true);

        return $response;
    }

    private function getModStatus(string $username, string $channel, int $page = 0): bool
    {
        $pageSize = self::SWORD_PAGESIZE;
        $response = $this->getSwords($username, $page, $pageSize);

        if($response['count'] > 0 && in_array(strtolower($channel), array_column($response['channels'], 'name'))) {
            return true;
        }

        if($response['count'] > ($page + 1) * $pageSize) {
            return $this->getModStatus($username, $channel, $page + 1);
        }
        return false;
    }

    private function getBTTVBots(string $channel): array
    {
        $url = "https://api.betterttv.net/2/channels/".$channel;
        $response = $this->client->get($url);

        if($response->getStatusCode() >= 400) {
            throw new Exception("Could not get BTTV bots");
        }

        $json = json_decode($response->getBody(), true);

        return $json['bots'];
    }

    private function checkFollowing(Submission $submission): bool
    {
        // Update following if needed
        if(!isset($submission->following)) {
            try {
                $follows = $this->twitch->getFollowing($submission->twitch_id);
            }
            catch(Exception $e) {
                $follows = new \stdClass();
                $follows->total = 0;
            }
            if($follows instanceof \stdClass) {
                $this->submissions->setFollowing($submission->id, $follows->total);
                $submission->following = $follows->total;
                return true;
            }
        }
        return false;
    }

    private function checkBio(Submission $submission): bool
    {
        if(!isset($submission->bio)) {
            try {
                $bio = $this->twitch->getBio($submission->twitch_id);
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

    private function checkHasVODs(Submission $submission): bool
    {
        if(!isset($submission->vods)) {
            try {
                $hasVODs = $this->twitch->hasVODs($submission->twitch_id);
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

    private function checkFollowingChannel(Submission $submission): bool
    {
        // Update following_channel if needed
        if(!isset($submission->following_channel)) {
            try {
                $follows_channel = $this->twitch->getFollowingChannel($submission->twitch_id, $submission->channel_id);
            }
            catch(Exception $e) {
                return false;
            }

            $this->submissions->setFollowingChannel($submission->id, $follows_channel);
            $submission->following_channel = $follows_channel;
            return true;
        }
        return false;
    }

    private function checkVerified(Submission $submission): bool
    {
        if(!isset($submission->verified) || !$submission->verified) {
            try {
                $verified = $this->twitch->getBotVerified($submission->twitch_id);
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

    public function checkBTTVBot(Submission $submission): bool
    {
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
                $this->submissions->setInChat($submission->id, true, true);
                $submission->online = true;
                return true;
            }
        }
        return false;
    }

    public function checkSubmissions(): int
    {
        $submissions = $this->submissions->getSubmissions();
        $count = 0;

        $requiredIds = [];
        $requiredNames = [];
        $channelsToCheck = [];

        foreach($submissions as $i => $submission) {
            $this->db->ping();
            $didSomething = false;

            // Update bot username or get twitch id
            if(empty($submission->twitch_id)) {
                $requiredIds[$submission->name] = $i;
            }
            else {
                $requiredNames[$submission->twitch_id] = $i;
            }

            if(!empty($submission->channel)) {
                // Update channel username or get twitch ID of it
                if(empty($submission->channel_id)) {
                    $requiredIds[$submission->channel] = $i;
                }
                else {
                    $requiredNames[$submission->channel_id] = $i;
                    if(!$submission->online || !isset($submission->offline)) {
                        $channelsToCheck[$submission->channel_id] = 1;
                    }
                }
            }
        }

        $results = $this->twitch->getChannelInfo(array_keys($requiredNames), array_keys($requiredIds));
        foreach($results as $user) {
            if(array_key_exists($user->login, $requiredIds)) {
                $index = $requiredIds[$user->login];
                unset($requiredIds[$user->login]);
                $submission = $submissions[$index];
                if($submission->name == $user->login) {
                    $submission->twitch_id = $user->id;
                    $this->submissions->setTwitchId($submission->id, $user->id);
                }
                else if($submission->channel == $user->login) {
                    $submission->channel_id = $user->id;
                    $this->submissions->setTwitchID($submission->id, $user->id, 'channel');
                    if(!$submission->online || !isset($submission->offline)) {
                        $channelsToCheck[$user->id] = 1;
                    }
                }
            }
            else if(array_key_exists($user->id, $requiredNames)) {
                $index = $requiredNames[$user->id];
                unset($requiredNames[$user->id]);
                $submission = $submissions[$index];
                if($submission->twitch_id == $user->id) {
                    if($submission->name != $user->login) {
                        $submission->name = $user->login;
                        $this->submissions->updateName($submission->id, $user->login);
                    }
                }
                else if($submission->channel_id == $user->id) {
                    if($submission->channel != $user->login) {
                        $submission->channel = $user->login;
                        $this->submissions->updateChannelName($submission->id, $user->login);
                    }
                }
            }
        }
        // Handle queries without response.
        foreach($requiredIds as $login => $index) {
            if(array_key_exists($index, $submissions)) {
                $submission = $submissions[$index];
                if($submission->name == $login) {
                    $this->submissions->removeSubmission($submission->id);
                    unset($submissions[$index]);
                }
                else if($submission->channel == $login) {
                    $this->submissions->clearChannel($submission->id);
                }
            }
        }
        foreach($requiredNames as $id => $index) {
            if(array_key_exists($index, $submissions)) {
                $submission = $submissions[$index];
                if($submission->twitch_id == $id) {
                    $this->submissions->removeSubmission($submission->id);
                    unset($submissions[$id]);
                }
                else if($submission->channel_id == $id) {
                    unset($channelsToCheck[$sid]);
                    $this->submissions->clearChannel($submission->id);
                }
            }
        }
        // normalize array keys.
        $submissions = array_values($submissions);
        $channelIds = array_keys($channelsToCheck);
        $channelStatuses = array_combine($channelIds, $this->twitch->findStreams($channelIds));

        foreach($submissions as $submission) {
            // if($this->bots->getBotByID($submission->twitch_id)) {
            //     $this->submissions->removeSubmission($submission->id);
            //     continue;
            // }
            $didSomething = false;

            // Check bot verification endpoints
            if($submission->type == 0 && $this->checkVerified($submission) && !$didSomething) {
                $didSomething = true;
            }
            if($submission->type == 0 && $this->checkBTTVBot($submission) && !$didSomething) {
                $didSomething = true;
            }

            // Get type if the user specified a type for the submission
            $type = null;
            if(is_numeric($submission->description)) {
                $type = $this->types->getTypeOrThrow((int)$submission->description);
            }

            // Update stuff that requires a channel to be set
            if(!empty($submission->channel) && !$submission->shouldApprove($type)) {
                if($this->checkFollowingChannel($submission) && !$didSomething) {
                    $didSomething = true;
                }

                $ranModCheck = isset($submission->ismod);
                // Update online or offline and mod if needed
                if(!$submission->online || !isset($submission->offline)) {
                    $live = isset($channelStatuses[$submission->channel_id]) && $channelStatuses[$submission->channel_id];
                    if(($live && !$submission->online) || (!$live && !isset($submission->offline) && !$submission->verified)) {
                        $isMod = null;
                        try {
                            $chatters = $this->twitch->getChatters($submission->channel);
                            $isInChannel = $this->isInChannel($submission->name, $chatters);

                            if($isInChannel && !$submission->ismod) {
                                $isMod = $this->isMod($submission->name, $chatters);
                            }
                            // Bot was not in user list and is not yet verified
                            else if(!$ranModCheck && !$submission->verified) {
                                $isMod = $this->getModStatus($submission->name, $submission->channel);
                            }
                        }
                        catch(Exception $e) {
                            $isInChannel = NULL;
                        }

                        // Save the info about bot that was just acquired
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

                // If user wasn't in channel chat and mod not set, get mod status
                if(!$ranModCheck && !$submission->verified) {
                    try {
                        $isMod = $this->getModStatus($submission->name, $submission->channel);
                    }
                    catch(Exception $e) {
                        $isMod = null;
                    }
                    if($isMod !== null) {
                        $this->submissions->setModded($submission->id, $isMod);
                        $submission->ismod = $isMod;
                    }
                    $didSomething = true;
                }
            }

            // If the bot is not to be auto approved, get more metadata on it.
            if(!$submission->shouldApprove($type)) {
                if($this->checkFollowing($submission) && !$didSomething) {
                    $didSomething = true;
                }
                if($this->checkBio($submission) && !$didSomething) {
                    $didSomething = true;
                }
                if($this->checkHasVODs($submission) && !$didSomething) {
                    $didSomething = true;
                }
            }

            if($didSomething) {
                ++$count;
                if($submission->shouldApprove($type)) {
                    $this->approveSubmission($submission->id);
                }
            }
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
        $ids = $this->twitch->getChannelInfo([], array_column($foundBots, 'name'));
        $needIds = [];
        foreach($foundBots as $i => $bot) {
            if(empty($this->bots->getBotByID($ids[$i]->id))) {
                $bot->twitch_id = $ids[$i]->id;
                if(!empty($bot->channel)) {
                    $needIds[$bot->channel] = $i;
                }
                else {
                    $this->bots->addBot($bot);
                    $count += 1;
                    // Remove any matching submissions.
                    $this->submissions->removeSubmissions($bot->twitch_id);
                }
            }
        }
        if(count($needIds)) {
            $channelIds = $this->twitch->getChannelInfo([], array_keys($needIds));
            foreach($channelIds as $user) {
                $index = $needIds[$user->login];
                $bot = $foundBots[$index];
                $bot->channel_id = $user->id;
                $this->bots->addBot($bot);
                $count += 1;
                // Remove any matching submissions.
                $this->submissions->removeSubmissions($bot->twitch_id);
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
        $submission = $this->submissions->getSubmissionOrThrow($id);
        if($submission->type != 0 && !is_numeric($submission->description)) {
            return false;
        }

        if(!empty($submission->twitch_id)) {
            $twitchId = $submission->twitch_id;
        }
        else {
            $twitchId = $this->twitch->getChannelID($submission->name);
        }

        if($submission->type != 0) {
            $bot = $this->bots->getBotByIDOrThrow($twitchId);
            if(is_numeric($submission->description)) {
                $bot->type = (int)$submission->description;
            }
            else {
                $bot->type = null;
            }
            $this->bots->updateBot($bot);
        }
        else {
            $channelId = null;
            if(!empty($submission->channel)) {
                if(!empty($submission->channel_id)) {
                    $channelId = $submission->channel_id;
                }
                else {
                    $channelId = $this->twitch->getChannelID($submission->channel);
                }
            }

            $bot = new Bot;
            $bot->twitch_id = $twitchId;
            $bot->name = $submission->name;
            $bot->channel = $submission->channel;
            $bot->channel_id = $channelId;

            if(is_numeric($submission->description)) {
                $bot->type = (int)$submission->description;
                $this->bots->addBot($bot);
            }
            else {
                $this->bots->addBot($bot);
                if(!empty($submission->description) && strlen($submission->description) > 5) {
                    $this->submissions->append($twitchId, $submission->name, 'From submissions: '.$submission->description, Submissions::CORRECTION, $submission->channel, $channelId);
                }
            }
        }

        if($submission->type == 0) {
            $this->submissions->removeSubmission($id);
        }
        else {
            $this->submissions->removeSubmissions($twitchId);
        }

        return true;
    }

    public function updateSubmission(int $id, string $description, string $channel)
    {
        $submission = $this->submissions->getSubmissionOrThrow($id);

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

    public function markSubmissionAsPerson(int $id)
    {
        $submission = $this->submissions->getSubmissionOrThrow($id);
        if(!empty($submission->twitch_id)) {
            $twitchId = $submission->twitch_id;
        }
        else {
            $twitchId = $this->twitch->getChannelID($submission->name);
        }
        $this->confirmedPeople->add($twitchId);
        $this->submissions->removeSubmission($id);
    }

    public function estimateActiveChannels(int $typeID): void
    {
        $type = $this->types->getTypeOrThrow($typeID);
        $count = 0;
        $channels = [];
        $pageSize = self::SWORD_PAGESIZE;
        $maxPagesPerInstance = 100;
        // Duplicates stop mattering > 10000 active channels.
        $maxCountForDetails = $pageSize * $maxPagesPerInstance;
        $descriptor = new BotListDescriptor();
        $descriptor->type = $typeID;
        $instCount = $this->bots->getCount($descriptor);
        $bots = $this->bots->getBotsByType($typeID, 0, $instCount);
        foreach($bots as $bot) {
            $estimated = false;
            if(!empty($bot->channel_id)) {
                $channels[$bot->channel] = true;
                $estimated = true;
            }
            if($type->multichannel) {
                $page = 0;
                do {
                    $response = $this->getSwords($bot->name, $page, $instCount === 1 ? 1 : $pageSize);

                    if($response['count'] > 0) {
                        if($response['count'] > $maxCountForDetails || $instCount === 1) {
                            $count += $response['count'];
                            $estimated = true;
                            break;
                        }
                        else {
                            foreach($response['channels'] as $channel) {
                                $channels[$channel['name']] = true;
                                $estimated = true;
                            }
                        }
                    }
                    else {
                        break;
                    }

                    $page += 1;
                } while($instCount > 1 && $response['count'] > $page * $pageSize && $response['count'] <= $maxCountForDetails);
            }

            if(!$estimated) {
                $count += 1;
            }
        }
        if (!$type->multichannel || $instCount > 1) {
            $count += count(array_keys($channels));
        }
        $this->db->ping();
        $this->types->setEstimate($typeID, $count);
    }
}
