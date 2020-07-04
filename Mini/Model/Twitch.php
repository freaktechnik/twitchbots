<?php

namespace Mini\Model;

use GuzzleHttp\Client;

class Twitch {
    private const KRAKEN_BASE = 'https://api.twitch.tv/kraken/';
    private const HELIX_BASE = 'https://api.twitch.tv/helix/';

    public const CLIENT_ID = 'client-ID';
    private const CLIENT_SECRET = 'client-secret';
    private const REFRESH_TOKEN = 'refresh-token';
    private const TOKEN = 'token';
    private const EXPIRES_IN = 'token-expires-in';
    private const GRANT_TIME = 'token-grant-time';

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

    private $clientID;
    private $clientSecret;
    private $refreshToken;
    private $token;
    private $expiresIn;
    private $grantTime;

    /** @var Config $config */
    private $config;

    function __construct(Client $client, Config $config, array $requestOptions)
    {
        $this->client = $client;
        $this->requestOptions = $requestOptions;

        $this->config = $config;
        $this->clientID = $config->get(self::CLIENT_ID);
        $this->clientSecret = $config->get(self::CLIENT_SECRET);
        $this->refreshToken = $config->get(self::REFRESH_TOKEN);
        $this->token = $config->get(self::TOKEN);
        $this->expiresIn = (int)$config->get(self::EXPIRES_IN, '-1');
        $this->grantTime = (int)$config->get(self::GRANT_TIME, '-1');

        $this->twitchHeaders = array_merge($requestOptions, array(
            'headers' => array('Client-ID' => $this->clientID)
        ));
        $this->twitchHeadersV5 = array_merge($requestOptions, [
            'headers' => [
                'Client-ID' => $this->clientID,
                'Accept' => 'application/vnd.twitchtv.v5+json'
            ]
        ]);
    }

    private function isTokenValid(): bool
    {
        return !empty($this->token) && ($this->expiresIn === 0 || $this->grantTime + $this->expiresIn > time());
    }

    private function getToken()
    {
        if(empty($this->refreshToken)) {
            $response = $this->client->post("https://id.twitch.tv/oauth2/token?client_id=".urlencode($this->clientID)."&client_secret=".urlencode($this->clientSecret)."&grant_type=client_credentials");
            if($response->getStatusCode() >= 400) {
                throw new \Exception("Could not get access token");
            }

            $data = json_decode($response->getBody());

            $this->token = $data->access_token;
            if(isset($data->refresh_token)) {
                $this->refreshToken = $data->refresh_token;
                $this->config->set(self::REFRESH_TOKEN, $this->refreshToken);
            }
            if(isset($data->expires_in)) {
                $this->expiresIn = $data->expires_in;
            }
            else {
                $this->expiresIn = 0;
            }
            $this->grantTime = time();

            $this->config->set(self::TOKEN, $this->token);
            $this->config->set(self::EXPIRES_IN, $this->expiresIn);
            $this->config->set(self::GRANT_TIME, $this->grantTime);
        }
        else {
            $response = $this->client->post("https://id.twitch.tv/oauth2/token", [
                'body' => '?grant_type=refresh_token&refresh_token='.urlencode($this->refreshToken).'&client_id='.urlencode($this->clientID).'&client_secret='.urlencode($this->clientSecret)
            ]);
            if($response->getStatusCode() > 500) {
                throw new \Exception("Twitch could not refresh the token");
            }
            else if($response->getStatusCode() >= 400) {
                $this->refreshToken = null;
                return $this->getToken();
            }

            $data = json_decode($response->getBody());

            $this->token = $data->access_token;
            if(isset($data->refresh_token)) {
                $this->refreshToken = $data->refresh_token;
                $this->config->set(self::REFRESH_TOKEN, $this->refreshToken);
            }
            if(isset($data->expires_in)) {
                $this->expiresIn = $data->expires_in;
                $this->config->set(self::EXPIRES_IN, $this->expiresIn);
            }
            $this->grantTime = time();

            $this->config->set(self::TOKEN, $this->token);
            $this->config->set(self::GRANT_TIME, $this->grantTime);
        }
    }

    private function makeHelixOptions(): array
    {
        if(!$this->isTokenValid()) {
            $this->getToken();
        }

        return [
            'headers' => array_merge($this->twitchHeaders['headers'], [
                'Authorization' => 'Bearer '.$this->token
            ])
        ];
    }

    private function shouldRefresh(\GuzzleHttp\Psr7\Response $response): bool
    {
        return $response->getStatusCode() === 401 && $response->hasHeader('WWW-Authenticate');
    }

    public function getChatters(string $channel): array
    {
        $response = $this->client->get("https://tmi.twitch.tv/group/user/".$channel."/chatters", $this->requestOptions);
        if($response->getStatusCode() >= 400) {
            throw new \Exception("No chatters returned");
        }

        return json_decode($response->getBody(), true)['chatters'];
    }

    /**
     * @param string[] $channelIds
     * @return bool[]
     */
    public function findStreams(array $channelIds): array
    {
        $idCount = count($channelIds);
        if(!$idCount) {
            return [];
        }
        $page = 0;
        $perPage = 100;
        $pageCount = ceil($idCount / $perPage);
        $baseURL = self::HELIX_BASE.'streams?';
        $ids = [];

        while($page < $pageCount) {
            $paramsOffset = $page * $perPage;
            ++$page;

            $url = $baseURL;
            $idParams = array_map(function(string $id): string {
                return 'user_id='.urlencode($id);
            }, array_slice($channelIds, $paramsOffset, $perPage));
            $url .= implode('&', $idParams);

            $response = $this->client->get($url, $this->makeHelixOptions());
            if($this->shouldRefresh($response)) {
                $this->getToken();
                --$page;
                continue;
            }
            else if($response->getStatusCode() >= 400) {
                throw new \Exception("Can not get live streams");
            }

            $data = json_decode($response->getBody())->data;
            $ids = array_merge($ids, array_column($data, 'user_id'));
        }

        return array_map(function(string $id) use ($ids): bool {
            return in_array($id, $ids);
        }, $channelIds);
    }

    public function getBio(string $channelId): ?string
    {
        $users = $this->getChannelInfo([ $channelId ]);
        if(isset($users[0]->description)) {
            return $users[0]->description;
        } else {
            return null;
        }
    }

    public function hasVODs(string $channelId): bool
    {
        $response = $this->client->get(self::HELIX_BASE.'videos?user_id='.$channelId, $this->makeHelixOptions());
        if($this->shouldRefresh($response)) {
            $this->getToken();
            return $this->hasVODs($channelId);
        }
        else if($response->getStatusCode() >= 400) {
            throw new \Exception("Can not get vods for ".$channelId);
        }
        /** @var \stdClass $vods */
        $vods = json_decode($response->getBody());
        return count($vods->data) > 0;
    }

    public function getFollowing(string $id): \stdClass
    {
        if(!in_array($id, $this->_followsCache)) {
            $response = $this->client->get(self::HELIX_BASE.'users/follows?from_id='.$id, $this->makeHelixOptions());
            if($this->shouldRefresh($response)) {
                $this->getToken();
                return $this->getFollowing($id);
            }
            else if($response->getStatusCode() >= 400) {
                throw new \Exception("Can not get followers for ".$id);
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
        $url = self::HELIX_BASE.'users/follows?from_id='.$id.'&to_id='.$channelId;
        $response = $this->client->get($url, $this->makeHelixOptions());
        if($this->shouldRefresh($response)) {
            $this->getToken();
            return $this->getFollowingChannel($id, $channelId);
        }
        else if($response->getStatusCode() >= 400) {
            throw new \Exception("Can't get following relation");
        }

        $follows = json_decode($response->getBody(), true)['data'];

        return !!count($follows);
    }

    public function getBotVerified(string $id): bool
    {
        $url = self::KRAKEN_BASE."users/".$id."/chat";
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
        $users = $this->getChannelInfo([], [ $username ]);
        return $users[0]->id;
    }

    public function getChannelName(string $id): string
    {
        $users = $this->getChannelInfo([ $id ]);
        return $users[0]->login;
    }

    /**
     * @param string[] $ids
     * @param string[] $names
     * @return \stdClass[]
     */
    public function getChannelInfo(array $ids = [], array $names = []): array
    {
        $baseURL = self::HELIX_BASE.'users/?';
        $idCount = count($ids);
        $nameCount = count($names);
        $maxCount = max($idCount, $nameCount);
        $perPage = 100;
        $pages = ceil($maxCount / $perPage);
        $page = 0;
        $users = [];
        while($page < $pages) {
            $params = [];
            $paramsOffset = $page * $perPage;
            $url = $baseURL;
            ++$page;

            if($idCount > $paramsOffset) {
                $params = array_merge($params, array_map(function(string $id) {
                    return 'id='.urlencode($id);
                }, array_slice($ids, $paramsOffset, $perPage)));
            }
            if($nameCount > $paramsOffset) {
                $params = array_merge($params, array_map(function(string $name) {
                    return 'login='.urlencode($name);
                }, array_slice($names, $paramsOffset, $perPage)));
            }
            if(!count($params)) {
                continue;
            }

            $url .= implode('&', $params);

            $response = $this->client->get($url, $this->makeHelixOptions());

            if($this->shouldRefresh($response)) {
                $this->getToken();
                --$page;
                continue;
            }
            else if($response->getStatusCode() >= 400) {
                throw new \Exception("Could not fetch user info", $response->getStatusCode());
            }

            $users = array_merge($users, json_decode($response->getBody())->data);
        }

        if(!count($users)) {
            throw new \Exception("No Twitch users returned");
        }

        return $users;
    }

    public function paginateUsersByName(array $names): array {
        $slice = 0;
        $nameCount = count($names);
        $sliceSize = 100;
        $result = [];
        while($slice * $sliceSize < $nameCount) {
            $nameSlice = array_slice($names, $slice * $sliceSize, $sliceSize);
            $sliceResult = $this->getChannelInfo([], $nameSlice);
            $result = array_merge($result, $sliceResult);
            $slice += 1;
        }

        return $result;
    }
}
