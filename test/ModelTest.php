<?php

use GuzzleHttp\Psr7\Response;

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
        $pdo = self::$pdo;
        $pdo->query('CREATE TABLE IF NOT EXISTS submissions (
            id int(10) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(535) CHARACTER SET ascii NOT NULL,
            description text NOT NULL,
            date timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            type int(1) unsigned NOT NULL DEFAULT 0,
            channel varchar(535) CHARACTER SET ascii DEFAULT NULL,
            offline boolean DEFAULT NULL,
            online boolean DEFAULT NULL,
            ismod boolean DEFAULT NULL,
            following int(10) unsigned DEFAULT NULL,
            following_channel boolean DEFAULT NULL,
            PRIMARY KEY (id)
        ) DEFAULT CHARSET=utf8 AUTO_INCREMENT=9');
        $pdo->query('CREATE TABLE IF NOT EXISTS types (
            id int(10) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(535) CHARACTER SET ascii NOT NULL,
            multichannel tinyint(1) NOT NULL,
            url text CHARACTER SET ascii NOT NULL,
            date timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY name (name)
        ) DEFAULT CHARSET=ascii AUTO_INCREMENT=37');
        $pdo->query('CREATE TABLE IF NOT EXISTS bots (
            name varchar(535) CHARACTER SET ascii NOT NULL,
            type int(10) unsigned DEFAULT NULL,
            date timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            channel varchar(535) CHARACTER SET ascii DEFAULT NULL,
            PRIMARY KEY (name),
            FOREIGN KEY (type) REFERENCES types(id)
        ) DEFAULT CHARSET=ascii');
        $pdo->query('CREATE TABLE IF NOT EXISTS config (
            name varchar(120) CHARACTER SET ascii NOT NULL,
            value varchar(100) CHARACTER SET ascii DEFAULT NULL,
            PRIMARY KEY (name)
        ) DEFAULT CHARSET=ascii');
        $pdo->query('CREATE TABLE IF NOT EXISTS check_tokens (
            id int(10) unsigned NOT NULL AUTO_INCREMENT,
            token varchar(535) CHARACTER SET ascii NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY(token)
        ) DEFAULT CHARSET=ascii AUTO_INCREMENT=2');
        $pdo->query('CREATE OR REPLACE VIEW count AS SELECT count(name) AS count FROM bots');
        $pdo->query('CREATE OR REPLACE VIEW list AS SELECT bots.name AS name, type, multichannel, types.name AS typename FROM bots LEFT JOIN types ON bots.type = types.id ORDER BY name ASC');
        $pdo->query('CREATE OR REPLACE VIEW typelist AS SELECT id, types.name AS name, multichannel, COUNT(DISTINCT(bots.name)) AS count FROM types LEFT JOIN bots ON bots.type = types.id GROUP BY id ORDER BY name ASC');

        parent::__construct();
    }

    /**
     * @return PHPUnit_Extensions_Database_DB_IDatabaseConnection
     */
    public function getConnection()
    {
        if ($this->conn === null) {
            if (self::$pdo == null) {
                self::$pdo = new PDO('mysql:dbname='.$GLOBALS['DB_NAME'].';host='.$GLOBALS['DB_HOST'].';port='.$GLOBALS['DB_PORT'], $GLOBALS['DB_USER'], $GLOBALS['DB_PASSWD']);
            }
            $this->conn = $this->createDefaultDBConnection(self::$pdo, ':memory:');
        }

        return $this->conn;
    }

    /**
     * @return PHPUnit_Extensions_Database_DataSet_IDataSet
     */
    public function getDataSet()
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

    public function testHasBot()
    {
        $this->assertTrue($this->model->hasBot('butler_of_ec0ke'));
        $this->assertFalse($this->model->hasBot('freaktechnik'));
        $this->httpMock->append(new Response(200));
        $this->model->addSubmission('freaktechnik', 1);
        $this->assertTrue($this->model->hasBot('freaktechnik'));
    }

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
     */
    public function testAddEmptySubmissionUsernameThrows()
    {
        $this->model->addSubmission("", 0, "lorem ipsum");
    }
    /**
     * @expectedException Exception
     * @expectedExceptionCode 9
     */
    public function testAddEmptySubmissionDescriptionThrows()
    {
        $this->model->addSubmission("test", 0, "");
    }
    /**
     * @expectedException Exception
     * @expectedExceptionCode 7
     */
    public function testAddSubmissionChannelEqualsUsernameThrows()
    {
        $this->model->addSubmission("test", 0, "lorem ipsum", "test");
    }
    /**
     * @expectedException Exception
     * @expectedExceptionCode 3
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
     */
    public function testAddSubmissionExistingBotThrows()
    {
        $this->httpMock->append(new Response(200));
        $this->model->addSubmission("nightbot", 2);
    }

    /**
     * @expectedException Exception
     * @expectedExceptionCode 12
     */
    public function testAddSubmissionChannelIsBotThrows()
    {
        $this->model->addSubmission("myboot", 1, "", "nightbot");
    }

    /**
     * @expectedException Exception
     * @expectedExceptionCode 13
     */
    public function testAddSubmissionBotIsChannelThrows()
    {
        $this->httpMock->append(new Response(200));
        $this->model->addSubmission("ec0ke", 2);
    }

    /**
     * @expectedException Exception
     * @expectedExceptionCode 2
     */
    public function testAddSubmissionNotOnTwitchThrows()
    {
        $this->httpMock->append(new Response(404));
        $this->model->addSubmission("notauser", 1);
    }

    /**
     * @expectedException Exception
     * @expectedExceptionCode 6
     */
    public function testAddSubmissionInexistentChannelThrows()
    {
        $this->httpMock->append(new Response(404));
        $this->model->addSubmission("bot", 22, "", "notauser");
    }

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
     */
    public function testAddEmptyCorrectionUsernameThrows()
    {
        $this->model->addCorrection("", 0, "lorem ipsum");
    }
    /**
     * @expectedException Exception
     * @expectedExceptionCode 4
     */
    public function testAddInexistingCorrectionThrows()
    {
        $this->model->addCorrection("test", 2);
    }
    /**
     * @expectedException Exception
     * @expectedExceptionCode 9
     */
    public function testAddEmptyCorrectionDescriptionThrows()
    {
        $this->model->addCorrection("nightbot", 0, "");
    }
    /**
     * @expectedException Exception
     * @expectedExceptionCode 5
     */
    public function testAddCorrectionSameTypeThrows()
    {
        $this->model->addCorrection("nightbot", 1);
    }

    public function testLock()
    {
        $this->assertFalse($this->model->checkRunning());
        $this->assertTrue($this->model->checkRunning());
        $this->model->checkDone();
        $this->assertFalse($this->model->checkRunning());
    }

    public function testCheckBots()
    {
        $initialCount = $this->model->bots->getCount();
        $bots = $this->model->checkBots();

        $this->assertEquals($initialCount - count($bots), $this->model->bots->getCount());
    }

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
     */
    public function testCanCheckThrowsEmptyString()
    {
        $this->model->canCheck(' ');
    }
    /**
     * @expectedException Exception
     */
    public function testCanCheckThrowsNonAlphanumerical1()
    {
        $this->model->canCheck('; DROP TABLE *;');
    }
    /**
     * @expectedException Exception
     */
    public function testCanCheckThrowsNonAlphanumerical2()
    {
        $this->model->canCheck(' ');
    }
    /**
     * @expectedException TypeError
     */
    public function testCanCheckThrowsNull()
    {
        $this->model->canCheck(null);
    }

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
?>
