<?php

require_once "DBTestCase.php";

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
            'users' => [
                [
                    '_id' => $id
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
    public function estimateActiveChannelsMultichannel()
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

        $this->assertEquals('https://twitchstuff.3v.fi/modlookup/api/user/test?limit=100&offset=0', $latestRequest['request']->getRequestTarget());
    }

    /**
     * @covers ::estimateActiveChannels
     * @covers ::<private>
     * @uses \Mini\Model\Bots::getCount
     * @uses \Mini\Model\Bots::getBotsByType
     * @uses \Mini\Model\Types::getTypeOrThrow
     * @uses \Mini\Model\Types::setEstimate
     */
    public function estimateActiveChannelsMultichannelMultiple()
    {
        $this->httpMock->append(new Response(200, [], json_encode([
            'status' => 200,
            'count' => 100,
            'channels' => [
                [
                    'name' => 'foo',
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

        $response = $this->model->estimateActiveChannels();

        $type = $this->model->types->getTypeOrThrow(1);
        $this->assertEquals(2, $type->channelsEstimate);

        $this->assertEquals($preCount + 2, count($this->httpHistory));
        /** @var \GuzzleHttp\Psr7\Request $latestRequest */
        $latestRequest = array_pop($this->httpHistory);

        $this->assertEquals('https://twitchstuff.3v.fi/modlookup/api/user/test?limit=100&offset=0', $latestRequest['request']->getRequestTarget());
    }

    /**
     * @expectedException Exception
     * @covers ::estimateActiveChannels
     * @covers ::<private>
     * @uses \Mini\Model\Bots::getCount
     * @uses \Mini\Model\Bots::getBotsByType
     * @uses \Mini\Model\Types::getTypeOrThrow
     */
    public function testGetSwordsError()
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
    public function testGetSwordsPagination()
    {
        $response = $this->model->estimateActiveChannels(22);

        $expectedCount = $this->model->bots->getCount(22);
        $type = $this->model->types->getTypeOrThrow(22);
        $this->assertEquals($expectedCount, $type->channelsEstimate);
    }

    //TODO test deduplicating channels without multi channel type
}
