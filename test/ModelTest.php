<?php

require_once "DBTestCase.php";

use Mini\Model\BotListDescriptor;

use GuzzleHttp\Psr7\Response;

/**
 * @coversDefaultClass \Mini\Model\Model
 */
class ModelTest extends DBTestCase
{
    /**
     * @var \Mini\Model\Model $model
     */
    private $model;

    /**
     * @var \GuzzleHttp\Handler\MockHandler $httpMock
     */
    private $httpMock;

    /**
     * @var array $httpHistory
     */
    private $httpHistory = [];

    /**
     * @var int
     */
    const pageSize = 100;

    public function __construct()
    {
        ob_start();

        parent::__construct();
    }

    public function setUp()
    {
        $this->httpMock = new \GuzzleHttp\Handler\MockHandler();
        $handlerStack = \GuzzleHttp\HandlerStack::create($this->httpMock);
        $handlerStack->push(\GuzzleHttp\Middleware::history($this->httpHistory));
        $client = new \GuzzleHttp\Client([
            'handler' => $handlerStack,
        ]);

        $this->model = new \Mini\Model\Model(array(
            'db_host' => $GLOBALS['DB_HOST'],
            'db_port' => $GLOBALS['DB_PORT'],
            'db_name' => $GLOBALS['DB_NAME'],
            'db_user' => $GLOBALS['DB_USER'],
            'db_pass' => $GLOBALS['DB_PASSWD'],
            'page_size' => self::pageSize,
            'testing' => true,
        ), $client);
        parent::setUp();
    }

    public function tearDown()
    {
        $this->httpMock = null;
        $this->model = null;
        parent::tearDown();
    }

    private function queueTwitchUser(string $id) {
        $this->httpMock->append(new Response(200, [], json_encode([
            'data' => [
                [
                    'id' => $id
                ]
            ]
        ])));
    }

    public function testCSRFTokenValidation()
    {
        $formname = "test";
        $token = $this->model->getToken($formname);
        $this->assertStringMatchesFormat('%s', $token);
        $this->assertTrue($this->model->checkToken($formname, $token));
    }

    /**
     * @covers ::getClientID
     * @uses \Mini\Model\Config::get
     * @uses \Mini\Model\Store::prepareSelect
     */
    public function testGetClientID()
    {
        $clientID = $this->model->getClientID();
        $this->assertEquals('loremipsum', $clientID);
    }

    /**
     * @covers ::hasBot
     * @uses \Mini\Model\Submissions::hasSubmission
     * @uses \Mini\Model\Bots::getBotByID
     */
    public function testHasBot()
    {
        $this->assertTrue($this->model->hasBot(4));
        $this->assertFalse($this->model->hasBot(33));
        $this->queueTwitchUser(33);
        $this->model->addSubmission('freaktechnik', 1);
        $this->assertTrue($this->model->hasBot(33));
    }

    /**
     * @covers ::addSubmission
     * @covers ::<private>
     * @uses \Mini\Model\Twitch::getChannelID
     * @uses \Mini\Model\Bots::getBotsByChannel
     * @uses \Mini\Model\Bots::getBot
     * @uses \Mini\Model\ConfirmedPeople::has
     * @uses \Mini\Model\Submissions::append
     * @uses \Mini\Model\Model::hasBot
     */
    public function testAddSubmission()
    {
        $this->assertEquals(0, $this->getConnection()->getRowCount('submissions'), "Pre-Condition");

        $this->queueTwitchUser("33");

        $this->model->addSubmission("test", 0, "lorem ipsum");

        $this->queueTwitchUser("34");

        $this->model->addSubmission("nightboot", 1);

        $this->queueTwitchUser("35");
        $this->model->addSubmission("notactuallyaboot", 44, "", "");

        $this->assertEquals(3, $this->getConnection()->getRowCount('submissions'), "Adding submission failed");

        $queryTable = $this->getConnection()->createQueryTable(
            'submissions', 'SELECT name, description, type FROM submissions'
        );
        $expectedTable = $this->createXMLDataSet(dirname(__FILE__)."/_fixtures/submissions.xml")
                              ->getTable("submissions");
        $this->assertTablesEqual($expectedTable, $queryTable);
    }

    /**
     * @expectedException Exception
     * @expectedExceptionCode 8
     * @covers ::addSubmission
     * @covers ::<private>
     */
    public function testAddEmptySubmissionUsernameThrows()
    {
        $this->model->addSubmission("", 0, "lorem ipsum");
    }
    /**
     * @expectedException Exception
     * @expectedExceptionCode 9
     * @covers ::addSubmission
     * @covers ::<private>
     */
    public function testAddEmptySubmissionDescriptionThrows()
    {
        $this->model->addSubmission("test", 0, "");
    }
    /**
     * @expectedException Exception
     * @expectedExceptionCode 7
     * @covers ::addSubmission
     * @covers ::<private>
     */
    public function testAddSubmissionChannelEqualsUsernameThrows()
    {
        $this->model->addSubmission("test", 0, "lorem ipsum", "test");
    }
    /**
     * @expectedException Exception
     * @expectedExceptionCode 3
     * @covers ::addSubmission
     * @covers ::<private>
     * @uses \Mini\Model\Twitch::getChannelID
     * @uses \Mini\Model\Bots::getBot
     * @uses \Mini\Model\Model::hasBot
     */
    public function testAddExistingSubmissionThrows()
    {
        $this->queueTwitchUser("31");
        $this->model->addSubmission("test", 1);
        $this->queueTwitchUser("31");
        $this->model->addSubmission("test", 2);
    }
    /**
     * @expectedException Exception
     * @expectedExceptionCode 3
     * @covers ::addSubmission
     * @covers ::<private>
     * @uses \Mini\Model\Twitch::getChannelID
     * @uses \Mini\Model\Bots::getBot
     * @uses \Mini\Model\Model::hasBot
     */
    public function testAddSubmissionExistingBotThrows()
    {
        $this->queueTwitchUser("15");
        $this->model->addSubmission("nightbot", 2);
    }

    /**
     * @expectedException Exception
     * @expectedExceptionCode 12
     * @covers ::addSubmission
     * @covers ::<private>
     * @uses \Mini\Model\Bots::getBot
     */
    public function testAddSubmissionChannelIsBotThrows()
    {
        $this->model->addSubmission("myboot", 1, "", "nightbot");
    }

    /**
     * @expectedException Exception
     * @expectedExceptionCode 13
     * @covers ::addSubmission
     * @covers ::<private>
     * @uses \Mini\Model\Twitch::getChannelID
     * @uses \Mini\Model\Bots::getBot
     * @uses \Mini\Model\Bots::getBotsByChannel
     */
    public function testAddSubmissionBotIsChannelThrows()
    {
        $this->queueTwitchUser("5");
        $this->model->addSubmission("ec0ke", 2);
    }

    /**
     * @expectedException Exception
     * @expectedExceptionCode 2
     * @covers ::addSubmission
     * @covers ::<private>
     * @uses \Mini\Model\Twitch::getChannelID
     * @uses \Mini\Model\Bots::getBot
     */
    public function testAddSubmissionNotOnTwitchThrows()
    {
        $this->httpMock->append(new Response(404));
        $this->model->addSubmission("notauser", 1);
    }

    /**
     * @expectedException Exception
     * @expectedExceptionCode 6
     * @covers ::addSubmission
     * @covers ::<private>
     * @uses \Mini\Model\Bots::getBot
     * @uses \Mini\Model\Twitch::getChannelID
     */
    public function testAddSubmissionInexistentChannelThrows()
    {
        $this->httpMock->append(new Response(404));
        $this->model->addSubmission("bot", 22, "", "notauser");
    }

    /**
     * @expectedException Exception
     * @expectedExceptionCode 14
     * @covers ::addSubmission
     * @covers ::<private>
     * @uses \Mini\Model\Bots::getBot
     * @uses \Mini\Model\Twitch::getChannelID
     * @uses \Mini\Model\Model::hasBot
     * @uses \Mini\Model\Bots::getBotsByChannel
     */
    public function testAddSubmissionConfirmedPersonThrows()
    {
        $this->queueTwitchUser('100');
        $this->model->addSubmission("bot", 22);
    }

    /**
     * @covers ::addCorrection
     * @covers ::<private>
     * @uses \Mini\Model\Bots::getBot
     * @uses \Mini\Model\Bots::getBotOrThrow
     * @uses \Mini\Model\Submissions::hasCorrection
     * @uses \Mini\Model\Submissions::append
     */
    public function testAddCorrection()
    {
        $this->assertEquals(0, $this->getConnection()->getRowCount('submissions'), "Pre-Condition");

        $this->model->addCorrection("moobot", 1);
        $this->model->addCorrection("nightbot", 0, "nightbot");
        $this->model->addCorrection("butler_of_ec0ke", 23, "");

        $this->assertEquals(3, $this->getConnection()->getRowCount('submissions'), "Adding correction failed");

        $queryTable = $this->getConnection()->createQueryTable(
           'submissions',
           'SELECT name, description, type FROM submissions'
        );
        $expectedTable = $this->createXMLDataSet(dirname(__FILE__)."/_fixtures/corrections.xml")
                              ->getTable("submissions");
        $this->assertTablesEqual($expectedTable, $queryTable);
    }

    /**
     * @expectedException Exception
     * @expectedExceptionCode 8
     * @covers ::addCorrection
     * @covers ::<private>
     */
    public function testAddEmptyCorrectionUsernameThrows()
    {
        $this->model->addCorrection("", 0, "lorem ipsum");
    }
    /**
     * @expectedException Exception
     * @expectedExceptionCode 4
     * @covers ::addCorrection
     * @covers ::<private>
     * @uses \Mini\Model\Bots::getBot
     * @uses \Mini\Model\Bots::getBotOrThrow
     */
    public function testAddInexistingCorrectionThrows()
    {
        $this->model->addCorrection("test", 2);
    }
    /**
     * @expectedException Exception
     * @expectedExceptionCode 9
     * @covers ::addCorrection
     */
    public function testAddEmptyCorrectionDescriptionThrows()
    {
        $this->model->addCorrection("nightbot", 0, "");
    }

    /**
     * @expectedException Exception
     * @expectedExceptionCode 5
     * @covers ::addCorrection
     * @covers ::<private>
     * @uses \Mini\Model\Bots::getBot
     * @uses \Mini\Model\Bots::getBotOrThrow
     */
    public function testAddCorrectionSameTypeThrows()
    {
        $this->model->addCorrection("nightbot", 1);
    }

    /**
     * @expectedException Exception
     * @expectedExceptionCode 11
     * @covers ::addCorrection
     * @covers ::<private>
     * @uses \Mini\Model\Bots::getBot
     * @uses \Mini\Model\Bots::getBotOrThrow
     * @uses \Mini\Model\Twitch::getChannelID
     */
    public function testAddCorrectionTwiceThrows()
    {
        $this->model->addCorrection("nightbot", 0, 'lorem');
        $this->model->addCorrection("nightbot", 0, 'lorem');
    }

    /**
     * @covers ::checkBots
     * @covers ::<private>
     * @uses \Mini\Model\Bots::getCount
     */
    public function testCheckBots()
    {
        $initialCount = $this->model->bots->getCount();
        $bots = $this->model->checkBots();

        $this->assertEquals($initialCount - count($bots), $this->model->bots->getCount());
    }

    /**
     * @covers ::estimateActiveChannels
     * @covers ::<private>
     * @uses \Mini\Model\Bots::getCount
     * @uses \Mini\Model\Bots::getBotsByType
     * @uses \Mini\Model\Types::getTypeOrThrow
     * @uses \Mini\Model\Types::setEstimate
     */
    public function testEstimateActiveChannelsMultichannel()
    {
        $this->httpMock->append(new Response(200, [], json_encode([
            'status' => 200,
            'count' => 5,
            'channels' => []
        ])));
        $preCount = count($this->httpHistory);

        $response = $this->model->estimateActiveChannels(1);

        $type = $this->model->types->getTypeOrThrow(1);
        $this->assertEquals(5, $type->channelsEstimate);

        $this->assertEquals($preCount + 1, count($this->httpHistory));
        /** @var \GuzzleHttp\Psr7\Request $latestRequest */
        $latestRequest = array_pop($this->httpHistory);

        $this->assertEquals('/modlookup/api/user/nightbot?limit=1&offset=0', $latestRequest['request']->getRequestTarget());
    }

    /**
     * @covers ::estimateActiveChannels
     * @covers ::<private>
     * @uses \Mini\Model\Bots::getCount
     * @uses \Mini\Model\Bots::getBotsByType
     * @uses \Mini\Model\Types::getTypeOrThrow
     * @uses \Mini\Model\Types::setEstimate
     */
    public function testEstimateActiveChannelsMultichannelMultiple()
    {
        $this->httpMock->append(new Response(200, [], json_encode([
            'status' => 200,
            'count' => 501,
            'channels' => [
                [
                    'name' => 'foo',
                ],
            ],
        ])));
        $this->httpMock->append(new Response(200, [], json_encode([
            'status' => 200,
            'count' => 501,
            'channels' => [
                [
                    'name' => 'bar',
                ],
            ],
        ])));
        $this->httpMock->append(new Response(200, [], json_encode([
            'status' => 200,
            'count' => 5,
            'channels' => [
                [
                    'name' => 'bar',
                ],
                [
                    'name' => 'foo',
                ],
            ],
        ])));
        $preCount = count($this->httpHistory);

        $response = $this->model->estimateActiveChannels(37);

        $type = $this->model->types->getTypeOrThrow(37);
        $this->assertEquals(2, $type->channelsEstimate);

        $this->assertEquals($preCount + 3, count($this->httpHistory));

        // The requests in reverse.
        /** @var \GuzzleHttp\Psr7\Request $latestRequest */
        $latestRequest = array_pop($this->httpHistory);

        $this->assertEquals('/modlookup/api/user/foo?limit=500&offset=0', $latestRequest['request']->getRequestTarget());

        /** @var \GuzzleHttp\Psr7\Request $latestRequest */
        $latestRequest = array_pop($this->httpHistory);

        $this->assertEquals('/modlookup/api/user/bar?limit=500&offset=500', $latestRequest['request']->getRequestTarget());

        /** @var \GuzzleHttp\Psr7\Request $latestRequest */
        $latestRequest = array_pop($this->httpHistory);

        $this->assertEquals('/modlookup/api/user/bar?limit=500&offset=0', $latestRequest['request']->getRequestTarget());
    }

    /**
     * @expectedException Exception
     * @covers ::estimateActiveChannels
     * @covers ::<private>
     * @uses \Mini\Model\Bots::getCount
     * @uses \Mini\Model\Bots::getBotsByType
     * @uses \Mini\Model\Types::getTypeOrThrow
     */
    public function testEstimateActiveChannelsError()
    {
        $this->httpMock->append(new Response(403, [], json_encode([
            'status' => 403,
            'count' => 0,
            'channels' => []
        ])));
        $response = $this->model->estimateActiveChannels(1);
    }

    /**
     * @covers ::estimateActiveChannels
     * @covers ::<private>
     * @uses \Mini\Model\Bots::getCount
     * @uses \Mini\Model\Bots::getBotsByType
     * @uses \Mini\Model\Types::getTypeOrThrow
     * @uses \Mini\Model\Types::setEstimate
     */
    public function testEstimateActiveChannelsLocal()
    {
        $response = $this->model->estimateActiveChannels(22);

        $descriptor = new BotListDescriptor();
        $descriptor->type = 22;
        $expectedCount = $this->model->bots->getCount($descriptor);
        $type = $this->model->types->getTypeOrThrow(22);
        // One duplicate channel param in fixture
        $this->assertEquals($expectedCount - 1, $type->channelsEstimate);
    }
}
