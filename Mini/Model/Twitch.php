<?php

namespace Mini\Model;

use GuzzleHttp\Client;

class Twitch {
    private static $krakenBase = 'https://api.twitch.tv/kraken/';

    /**
     * The guzzle client
     * @var Client $client
     */
    private $client;

    private $requestOptions = [];
    private $twitchHeaders = [];
    private $twitchHeadersV5 = [];

    /** @var \stdClass[] $_followsCache */
    private $_followsCache = [];

    function __construct(Client $client, string $clientID, array $requestOptions) {
        $this->client = $client;
        $this->requestOptions = $requestOptions;

        $this->twitchHeaders = array_merge($requestOptions, array(
            'headers' => array('Client-ID' => $clientID, 'Accept' => 'application/vnd.twitchtv.v3+json')
        ));
        $this->twitchHeadersV5 = array_merge($requestOptions, [
            'headers' => [
                'Client-ID' => $clientID,
                'Accept' => 'application/vnd.twitchtv.v5+json'
            ]
        ]);
    }

    public function userExists(string $id, $noJustin = false): bool
    {
        $response = $this->client->head(self::$krakenBase."users/".$id, $this->twitchHeadersV5);
        $http_code = $response->getStatusCode();
        return $http_code != 404 && (!$noJustin || $http_code != 422);
    }

    public function getChatters(string $channel): array
    {
        $response = $this->client->get("https://tmi.twitch.tv/group/user/".$channel."/chatters", [], $this->requestOptions);
        if($response->getStatusCode() >= 400) {
            throw new \Exception("No chatters returned");
        }

        return json_decode($response->getBody(), true)['chatters'];
    }

    public function isChannelLive(string $channelId): bool
    {
        $response = $this->client->get(self::$krakenBase.'streams/'.$channelId, $this->twitchHeadersV5);

        /** @var \stdClass $stream */
        $stream = json_decode($response->getBody());
        return isset($stream->stream);
    }

    public function getBio(string $channelId)//: ?string
    {
        $response = $this->client->get(self::$krakenBase.'users/'.$channelId, $this->twitchHeadersV5);
        /** @var \stdClass $user */
        $user = json_decode($response->getBody());
        if(isset($user->bio)) {
            return $user->bio;
        } else {
            return NULL;
        }
    }

    public function hasVODs(string $channelId): bool
    {
        $response = $this->client->get(self::$krakenBase.'channels/'.$channelId.'/videos?broadcast_type=archive,highlight,upload', $this->twitchHeadersV5);
        /** @var \stdClass $vods */
        $vods = json_decode($response->getBody());
        return $vods->_total > 0;
    }

    public function getFollowing(string $id): \stdClass
    {
        if(!in_array($id, $this->_followsCache)) {
            $response = $this->client->get(self::$krakenBase.'users/'.$id.'/follows/channels', $this->twitchHeadersV5);

            if($response->getStatusCode() >= 400) {
                throw new \Exception("Can not get followers for ".$name);
            }

            $this->_followsCache[$id] = json_decode($response->getBody());
        }
        return $this->_followsCache[$id];
    }

    public function getFollowingChannel(string $id, string $channelId): bool
    {
        if(in_array($id, $this->_followsCache)) {
            $follows = $this->_followsCache[$id];
            if($follows instanceof \stdClass) {
                $followsChannel = $follows->_total > 0 && in_array($channelId, array_map(function(\stdClass $chan) {
                    /** @var \stdClass $channel */
                    $channel = $chan->channel;
                    return strtolower($channel->_id);
                }, $follows->follows));
                if($follows->_total <= count($follows->follows)) {
                    return $followsChannel;
                }
            }
        }
        $url = self::$krakenBase.'users/'.$id.'/follows/channels/'.$channelId;
        $response = $this->client->head($url, $this->twitchHeadersV5);

        if($response->getStatusCode() >= 400 && $response->getStatusCode() !== 404) {
            throw new \Exception("Can't get following relation");
        }

        return $response->getStatusCode() < 400;
    }

    public function getBotVerified(string $id): bool {
        $url = self::$krakenBase."users/".$id."/chat";
        $response = $this->client->get($url, $this->twitchHeadersV5);

        if($response->getStatusCode() >= 400) {
            throw new \Exception("Could not get verified status");
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

    public function getChannelID(string $username): string
    {
        $url = self::$krakenBase.'users/?login='.$username;
        $response = $this->client->get($url, $this->twitchHeadersV5);

        if($response->getStatusCode() >= 400 && $response->getStatusCode() !== 404) {
            throw new \Exception("User could not be found");
        }

        $users = json_decode($response->getBody(), true)['users'];
        if(count($users) > 0){
            return $users[0]['_id'];
        }
        else {
            throw new \Exception("User could not be found");
        }
    }

    public function getChannelName(string $id): string
    {
        $url = self::$krakenBase.'users/'.$id;
        $response = $this->client->get($url, $this->twitchHeadersV5);

        if($response->getStatusCode() >= 400) {
            throw new \Exception("Could not get username for ".$id, $response->getStatusCode());
        }

        $user = json_decode($response->getBody(), true);
        return $user['name'];
    }
}
