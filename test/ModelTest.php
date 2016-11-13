<?php

include_once('_fixtures/setup.php');

use GuzzleHttp\Psr7\Response;

/**
 * @coversDefaultClass \Mini\Model\Model
 */
class ModelTest extends PHPUnit_Extensions_Database_TestCase
{
    // Database connection efficieny
    static private $pdo = null;
    private $conn = null;

    /**
     * @var \Mini\Model\Model
     */
    private $model;

    /**
     * @var \Aeris\GuzzleHttp\Mock
     */
    private $httpMock;

    /**
     * @var int
     */
    const pageSize = 100;

    public function __construct()
    {
        // We need this so sessions work
        ob_start();

        $this->getConnection();
        create_tables(self::$pdo);

        parent::__construct();
    }

    public function getConnection(): PHPUnit_Extensions_Database_DB_IDatabaseConnection
    {
        if ($this->conn === null) {
            if (self::$pdo == null) {
                self::$pdo = new PDO('mysql:dbname='.$GLOBALS['DB_NAME'].';host='.$GLOBALS['DB_HOST'].';port='.$GLOBALS['DB_PORT'], $GLOBALS['DB_USER'], $GLOBALS['DB_PASSWD']);
            }
            $this->conn = $this->createDefaultDBConnection(self::$pdo, ':memory:');
        }

        return $this->conn;
    }

    public function getDataSet(): PHPUnit_Extensions_Database_DataSet_IDataSet
    {
        return $this->createXMLDataSet(dirname(__FILE__).'/_fixtures/bots.xml');
    }

    public function setUp()
    {
        $this->httpMock = new \GuzzleHttp\Handler\MockHandler();
        $client = new \GuzzleHttp\Client(array(
            'handler' => \GuzzleHttp\HandlerStack::create($this->httpMock)
        ));

        $this->model = new \Mini\Model\Model(array(
            'db_host' => $GLOBALS['DB_HOST'],
            'db_port' => $GLOBALS['DB_PORT'],
            'db_name' => $GLOBALS['DB_NAME'],
            'db_user' => $GLOBALS['DB_USER'],
            'db_pass' => $GLOBALS['DB_PASSWD'],
            'page_size' => self::pageSize,
            'testing' => true
        ), $client);
        parent::setUp();
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
     */
    public function testHasBot()
    {
        $this->assertTrue($this->model->hasBot('butler_of_ec0ke'));
        $this->assertFalse($this->model->hasBot('freaktechnik'));
        $this->httpMock->append(new Response(200));
        $this->model->addSubmission('freaktechnik', 1);
        $this->assertTrue($this->model->hasBot('freaktechnik'));
    }

    /**
     * @covers ::addSubmission
     */
    public function testAddSubmission()
    {
        $this->assertEquals(0, $this->getConnection()->getRowCount('submissions'), "Pre-Condition");

        $this->httpMock->append(new Response(200));

        $this->model->addSubmission("test", 0, "lorem ipsum");

        $this->httpMock->append(new Response(200));

        $this->model->addSubmission("nightboot", 1);

        $this->httpMock->append(new Response(200));
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
     */
    public function testAddEmptySubmissionUsernameThrows()
    {
        $this->model->addSubmission("", 0, "lorem ipsum");
    }
    /**
     * @expectedException Exception
     * @expectedExceptionCode 9
     * @covers ::addSubmission
     */
    public function testAddEmptySubmissionDescriptionThrows()
    {
        $this->model->addSubmission("test", 0, "");
    }
    /**
     * @expectedException Exception
     * @expectedExceptionCode 7
     * @covers ::addSubmission
     */
    public function testAddSubmissionChannelEqualsUsernameThrows()
    {
        $this->model->addSubmission("test", 0, "lorem ipsum", "test");
    }
    /**
     * @expectedException Exception
     * @expectedExceptionCode 3
     * @covers ::addSubmission
     */
    public function testAddExistingSubmissionThrows()
    {
        $this->httpMock->append(new Response(200));
        $this->model->addSubmission("test", 1);
        $this->httpMock->append(new Response(200));
        $this->model->addSubmission("test", 2);
    }
    /**
     * @expectedException Exception
     * @expectedExceptionCode 3
     * @covers ::addSubmission
     */
    public function testAddSubmissionExistingBotThrows()
    {
        $this->httpMock->append(new Response(200));
        $this->model->addSubmission("nightbot", 2);
    }

    /**
     * @expectedException Exception
     * @expectedExceptionCode 12
     * @covers ::addSubmission
     */
    public function testAddSubmissionChannelIsBotThrows()
    {
        $this->model->addSubmission("myboot", 1, "", "nightbot");
    }

    /**
     * @expectedException Exception
     * @expectedExceptionCode 13
     * @covers ::addSubmission
     */
    public function testAddSubmissionBotIsChannelThrows()
    {
        $this->httpMock->append(new Response(200));
        $this->model->addSubmission("ec0ke", 2);
    }

    /**
     * @expectedException Exception
     * @expectedExceptionCode 2
     * @covers ::addSubmission
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
     */
    public function testAddSubmissionInexistentChannelThrows()
    {
        $this->httpMock->append(new Response(404));
        $this->model->addSubmission("bot", 22, "", "notauser");
    }

    /**
     * @covers ::addCorrection
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
     */
    public function testAddEmptyCorrectionUsernameThrows()
    {
        $this->model->addCorrection("", 0, "lorem ipsum");
    }
    /**
     * @expectedException Exception
     * @expectedExceptionCode 4
     * @covers ::addCorrection
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
     */
    public function testAddCorrectionSameTypeThrows()
    {
        $this->model->addCorrection("nightbot", 1);
    }

    /**
     * @covers ::checkRunning
     * @covers ::checkDone
     */
    public function testLock()
    {
        $this->assertFalse($this->model->checkRunning());
        $this->assertTrue($this->model->checkRunning());
        $this->model->checkDone();
        $this->assertFalse($this->model->checkRunning());
    }

    /**
     * @covers ::checkBots
     * @uses \Mini\Model\Bots::getCount
     */
    public function testCheckBots()
    {
        $initialCount = $this->model->bots->getCount();
        $bots = $this->model->checkBots();

        $this->assertEquals($initialCount - count($bots), $this->model->bots->getCount());
    }

    /**
     * @covers ::twitchUserExists
     */
    public function testTwitchUserExists()
    {
        $this->httpMock->append(new Response(200));
        $this->assertTrue($this->model->twitchUserExists('butler_of_ec0ke'));
        $this->httpMock->append(new Response(302));
        $this->assertTrue($this->model->twitchUserExists('xanbot'));
        $this->httpMock->append(new Response(404));
        $this->assertFalse($this->model->twitchUserExists('zeldbot'));
    }

    /**
     * @expectedException Exception
     * @covers ::canCheck
     */
    public function testCanCheckThrowsEmptyString()
    {
        $this->model->canCheck(' ');
    }
    /**
     * @expectedException Exception
     * @covers ::canCheck
     */
    public function testCanCheckThrowsNonAlphanumerical1()
    {
        $this->model->canCheck('; DROP TABLE *;');
    }
    /**
     * @expectedException Exception
     * @covers ::canCheck
     */
    public function testCanCheckThrowsNonAlphanumerical2()
    {
        $this->model->canCheck(' ');
    }
    /**
     * @expectedException TypeError
     * @covers ::canCheck
     */
    public function testCanCheckThrowsNull()
    {
        $this->model->canCheck(null);
    }

    /**
     * @covers ::canCheck
     */
    public function testCanCheck()
    {
        $this->assertFalse($this->model->canCheck('foobar'));
        $this->assertTrue($this->model->canCheck('foobar0'));
        $this->assertFalse($this->model->canCheck('FooBar0'));
        $this->assertFalse($this->model->canCheck('0'));
        $this->assertFalse($this->model->canCheck('null'));
        $this->assertFalse($this->model->canCheck('false'));
    }
}
