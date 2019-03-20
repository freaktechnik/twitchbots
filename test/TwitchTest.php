<?php

require_once "DBTestCase.php";

use GuzzleHttp\Psr7\Response;

/**
 * @coversDefaultClass \Mini\Model\Twitch
 */
class TwitchTest extends DBTestCase
{
    /**
     * @var \Mini\Model\Twitch
     */
    private $twitch;

    /**
     * @var \Aeris\GuzzleHttp\Mock
     */
    private $httpMock;

    public function __construct()
    {
        ob_start();

        parent::__construct();
    }

    public function setUp()
    {
        $this->httpMock = new \GuzzleHttp\Handler\MockHandler();
        $client = new \GuzzleHttp\Client(array(
            'handler' => \GuzzleHttp\HandlerStack::create($this->httpMock)
        ));
        $this->twitch = new \Mini\Model\Twitch($client, new \Mini\Model\Config(self::$pdo), ['http_errors' => false]);
        $this->httpMock->append(new Response(200, [], json_encode([
            'access_token' => 'asdf',
            'refresh_token' => 'asdfasdf',
            'expires_in' => 6000
        ])));
        parent::setUp();
    }

    public function tearDown()
    {
        $this->httpMock = null;
        $this->twitch = null;
        parent::tearDown();
    }

    private function queueSuccessfulRequest()
    {
        $this->httpMock->append(new Response(200, [], json_encode([
            'data' => [
                [
                    'user_id' => 1
                ]
            ]
        ])));
    }

    /**
     * @covers ::findStreams
     */
    public function testFindStreamsHundred()
    {
        $this->queueSuccessfulRequest();
        $this->queueSuccessfulRequest();
        $res = $this->twitch->findStreams(array_fill(0, 101, 1));
        $this->assertTrue($res[0]);
    }

    /**
     * @covers ::getChannelInfo
     */
    public function testChannelInfoHundredIDs()
    {
        $this->queueSuccessfulRequest();
        $this->queueSuccessfulRequest();
        $res = $this->twitch->getChannelInfo(array_fill(0, 101, 1));
        $this->assertCount(2, $res);
    }

    /**
     * @covers ::getChannelInfo
     */
    public function testChannelInfoHundredNames()
    {
        $this->queueSuccessfulRequest();
        $this->queueSuccessfulRequest();
        $res = $this->twitch->getChannelInfo([], array_fill(0, 101, 1));
        $this->assertCount(2, $res);
    }
}
