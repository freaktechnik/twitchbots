<?php

namespace Mini\Model;

use GuzzleHttp\Client;

class Twitch {
    private static $krakenBase = 'https://api.twitch.tv/kraken/';
    private static $helixBase = 'https://api.twitch.tv/helix/';

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
        $response = $this->client->get(self::$helixBase.'streams?user_id='.$channelId, $this->twitchHeaders);

        /** @var \stdClass $stream */
        $stream = json_decode($response->getBody());
        return $stream->data && count($stream->data);
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
        $response = $this->client->get(self::$helixBase.'videos?user_id='.$channelId, $this->twitchHeaders);
        /** @var \stdClass $vods */
        $vods = json_decode($response->getBody());
        return count($vods->data) > 0;
    }

    public function getFollowing(string $id): \stdClass
    {
        if(!in_array($id, $this->_followsCache)) {
            $response = $this->client->get(self::$helixBase.'users/follows?from_id='.$id, $this->twitchHeaders);

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
                $followsChannel = $follows->total > 0 && in_array($channelId, array_map(function(\stdClass $chan) {
                    return $chan->to_id;
                }, $follows->data));
                if($follows->total <= count($follows->data)) {
                    return $followsChannel;
                }
            }
        }
        $url = self::$helixBase.'users/follows?from_id='.$id.'&to_id='.$channelId;
        $response = $this->client->get($url, $this->twitchHeaders);

        if($response->getStatusCode() >= 400) {
            throw new \Exception("Can't get following relation");
        }

        $follows = json_decode($response->getBody(), true)['data'];

        return !!count($follows);
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
        $url = self::$helixBase.'users/?login='.$username;
        $response = $this->client->get($url, $this->twitchHeaders);

        if($response->getStatusCode() >= 400 && $response->getStatusCode() !== 404) {
            throw new \Exception("User could not be found");
        }

        $users = json_decode($response->getBody(), true)['data'];
        if(count($users) > 0){
            return $users[0]['id'];
        }
        throw new \Exception("User could not be found");
    }

    public function getChannelName(string $id): string
    {
        $url = self::$helixBase.'users/?id='.$id;
        $response = $this->client->get($url, $this->twitchHeaders);

        if($response->getStatusCode() >= 400) {
            throw new \Exception("Could not get username for ".$id, $response->getStatusCode());
        }

        $users = json_decode($response->getBody(), true)['data'];

        if(!count($users)) {
            throw new \Exception("No Twitch users returned for ID ".$id, $response->getStatusCode());
        }
        return $users[0]['login'];
    }
}
