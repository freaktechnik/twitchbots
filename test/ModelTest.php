<?php

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
            token varchar(535) CHARACTER SET ascii_bin NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY(token)
        ) DEFAULT CHARSET=ascii_bin AUTO_INCREMENT=2');
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
        $this->model = new \Mini\Model\Model(array(
            'db_host' => $GLOBALS['DB_HOST'],
            'db_port' => $GLOBALS['DB_PORT'],
            'db_name' => $GLOBALS['DB_NAME'],
            'db_user' => $GLOBALS['DB_USER'],
            'db_pass' => $GLOBALS['DB_PASSWD'],
            'page_size' => self::pageSize,
            'testing' => true
        ));
        parent::setUp();
    }

    public function testCSRFTokenValidation()
    {
        $formname = "test";
        $token = $this->model->getToken($formname);
        $this->assertStringMatchesFormat('%s', $token);
        $this->assertTrue($this->model->checkToken($formname, $token));
    }

    public function testAddSubmission()
    {
        $this->assertEquals(0, $this->getConnection()->getRowCount('submissions'), "Pre-Condition");

        $this->model->addSubmission("test", 0, "lorem ipsum");
        $this->model->addSubmission("nightboot", 1);

        $this->assertEquals(2, $this->getConnection()->getRowCount('submissions'), "Adding submission failed");

        $queryTable = $this->getConnection()->createQueryTable(
            'submissions', 'SELECT name, description FROM submissions'
        );
        $expectedTable = $this->createXMLDataSet(dirname(__FILE__)."/_fixtures/submissions.xml")
                              ->getTable("submissions");
        $this->assertTablesEqual($expectedTable, $queryTable);
    }

    /**
     * @expectedException Exception
     * @expectedExceptionCode 0
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
        $this->model->addSubmission("test", 1);
        $this->model->addSubmission("test", 2);
    }
    /**
     * @expectedException Exception
     * @expectedExceptionCode 3
     */
    public function testAddSubmissionExistingBotThrows()
    {
        $this->model->addSubmission("nightbot", 2);
    }

    public function testAddCorrection()
    {
        $this->assertEquals(0, $this->getConnection()->getRowCount('submissions'), "Pre-Condition");

        $this->model->addCorrection("moobot", 1);
        $this->model->addCorrection("nightbot", 0, "nightbot");
        $this->assertEquals(2, $this->getConnection()->getRowCount('submissions'), "Adding submission failed");

        $queryTable = $this->getConnection()->createQueryTable(
           'submissions',
           'SELECT name, description FROM submissions'
        );
        $expectedTable = $this->createXMLDataSet(dirname(__FILE__)."/_fixtures/corrections.xml")
                              ->getTable("submissions");
        $this->assertTablesEqual($expectedTable, $queryTable);
    }

    /**
     * @expectedException Exception
     * @expectedExceptionCode 0
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
    /**
     * @expectedException Exception
     * @expectedExceptionCode 7
     */
    public function testAddCorrectionChannelEqualsUsernameThrows()
    {
        $this->model->addCorrection("nightbot", 0, "lorem ipsum", "nightbot");
    }
    /**
     * @expectedException Exception
     * @expectedExceptionCode 5
     */
    public function testAddCorrectionIdenticalChannelThrows()
    {
        $this->model->addCorrection("butler_of_ec0ke", 22, "", "ec0ke");
    }
    /**
     * @expectedException Exception
     * @expectedExceptionCode 10
     */
    public function testAddCorrectionMultichannelChannelThrows()
    {
        $this->model->addCorrection('nightbot', 1, "", "night");
    }

    public function testGetSubmissions()
    {
        $this->assertEquals(count($this->model->getSubmissions()), $this->getConnection()->getRowCount('submissions'), "Not an empty array with no submissions");

        $this->model->addSubmission("test", 0, "lorem ipsum");
        $this->model->addSubmission("nightboot", 1);
        $this->assertEquals(2, $this->getConnection()->getRowCount('submissions'), "Test setup failed");

        $submissions = $this->model->getSubmissions();

        $this->assertCount($this->getConnection()->getRowCount('submissions'), $submissions);

        foreach($submissions as $submission) {
            $this->assertObjectHasAttribute("name", $submission);
            $this->assertObjectHasAttribute("description", $submission);
            $this->assertObjectHasAttribute("date", $submission);
            $this->assertGreaterThanOrEqual(strtotime($submission->date), time());
            $this->assertObjectHasAttribute("id", $submission);
            $this->assertObjectHasAttribute("offline", $submission);
            $this->assertObjectHasAttribute("online", $submission);
            $this->assertObjectHasAttribute("ismod", $submission);
        }

        // Sort order is descending by timestamp
        //TODO test array?
    }

    public function testGetLastSubmissionsUpdate()
    {
        $this->model->addSubmission("test", 1);

        $submissions = $this->model->getSubmissions();
        $lastModified = $this->model->getLastUpdate('submissions');

        $this->assertEquals($lastModified, strtotime($submissions[0]->date));
    }

    public function testGetPageCount()
    {
        for($count = 0; $count < self::pageSize * 4; ++$count) {
            $expectedPageCount = ceil($count / (float)self::pageSize);
            $pageCount = $this->model->getPageCount(self::pageSize, $count);

            $this->assertEquals($expectedPageCount, $pageCount);
        }
    }

    public function testGetOffset()
    {
        for($page = 1; $page < 4; ++$page) {
            $expectedOffset = ($page - 1) * self::pageSize;
            $offset = $this->model->getOffset($page);

            $this->assertEquals($expectedOffset, $offset);
        }
    }

    public function testGetType()
    {
        $type = $this->model->getType(1);

        $this->assertEquals("Nightbot", $type->name);
        $this->assertEquals(1, $type->id);
        $this->assertEquals(true, $type->multichannel);
        $this->assertEquals("https://www.nightbot.tv/", $type->url);
    }

    public function testGetNotExistingType()
    {
        $type = $this->model->getType(0);

        $this->assertFalse($type);
    }

    public function testGetAllTypes()
    {
        $types = $this->model->getAllTypes();

        $this->assertCount($this->getConnection()->getRowCount('types'), $types);

        foreach($types as $type) {
            $this->assertObjectHasAttribute("name", $type);
            $this->assertObjectHasAttribute("id", $type);
            $this->assertObjectHasAttribute("url", $type);
            $this->assertObjectHasAttribute("multichannel", $type);
            $this->assertObjectHasAttribute("date", $type);
            $this->assertGreaterThanOrEqual(strtotime($type->date), time());
        }
    }

    public function testGetBot()
    {
        $bot = $this->model->getBot("butler_of_ec0ke");

        $this->assertEquals("butler_of_ec0ke", $bot->name);
        $this->assertEquals(22, $bot->type);
        $this->assertGreaterThanOrEqual(strtotime($bot->date), time());
        $this->assertEquals("ec0ke", $bot->channel);
    }

    public function testGetNotExistingBot()
    {
        $bot = $this->model->getBot("freaktechnik");

        $this->assertFalse($bot);
    }

    public function testGetBotsByType()
    {
        $bots = $this->model->getBotsByType(22);

        $queryTable = $this->getConnection()->createQueryTable(
            'bots', 'SELECT name FROM bots WHERE type=22 LIMIT '.self::pageSize
        );

        $this->assertCount($queryTable->getRowCount(), $bots);

        foreach($bots as $bot) {
            $this->assertObjectHasAttribute("name", $bot);
            $this->assertObjectHasAttribute("type", $bot);
            $this->assertObjectHasAttribute("date", $bot);
            $this->assertGreaterThanOrEqual(strtotime($bot->date), time());
        }

        $bots = $this->model->getBotsByType(22, self::pageSize);
        $queryTable = $this->getConnection()->createQueryTable(
            'bots', 'SELECT name FROM bots WHERE type=22 LIMIT '.self::pageSize.','.self::pageSize
        );
        $this->assertCount($queryTable->getRowCount(), $bots);
    }

    public function testGetBotCount()
    {
        $botCount = $this->model->getBotCount();
        $this->assertEquals($botCount, $this->getConnection()->getRowCount('bots'));

        $botCount = $this->model->getBotCount(22);
        $queryTable = $this->getConnection()->createQueryTable(
            'bots', 'SELECT name FROM bots WHERE type=22'
        );

        $this->assertEquals($botCount, $queryTable->getRowCount());
    }

    public function testGetBots()
    {
        $bots = $this->model->getBots();

        $queryTable = $this->getConnection()->createQueryTable(
            'bots', 'SELECT name FROM bots LIMIT '.self::pageSize
        );

        $this->assertCount($queryTable->getRowCount(), $bots);

        foreach($bots as $bot) {
            $this->assertObjectHasAttribute("name", $bot);
            $this->assertObjectHasAttribute("type", $bot);
            $this->assertObjectHasAttribute("multichannel", $bot);
            $this->assertObjectHasAttribute("typename", $bot);
        }

        $bots = $this->model->getBots(2);
        $queryTable = $this->getConnection()->createQueryTable(
            'bots', 'SELECT name FROM bots LIMIT '.self::pageSize.','.self::pageSize
        );
        $this->assertCount($queryTable->getRowCount(), $bots);
    }

    public function testGetAllRawBots()
    {
        $bots = $this->model->getAllRawBots();

        $queryTable = $this->getConnection()->createQueryTable(
            'bots', 'SELECT name FROM bots LIMIT '.self::pageSize
        );

        $this->assertCount($queryTable->getRowCount(), $bots);

        foreach($bots as $bot) {
            $this->assertObjectHasAttribute("name", $bot);
            $this->assertObjectHasAttribute("type", $bot);
            $this->assertObjectHasAttribute("date", $bot);
            $this->assertGreaterThanOrEqual(strtotime($bot->date), time());
        }

        $bots = $this->model->getAllRawBots(self::pageSize);
        $queryTable = $this->getConnection()->createQueryTable(
            'bots', 'SELECT name FROM bots LIMIT '.self::pageSize.','.self::pageSize
        );
        $this->assertCount($queryTable->getRowCount(), $bots);
    }

    public function testGetBotsByNames()
    {
        $names = array(
            "butler_of_ec0ke",
            "freaktechnik",
            "nightbot",
            "ec0ke",
            "syntria"
        );
        $bots = $this->model->getBotsByNames($names);

        $this->assertCount(2, $bots);

        foreach($bots as $bot) {
            $this->assertObjectHasAttribute("name", $bot);
            $this->assertObjectHasAttribute("type", $bot);
            $this->assertObjectHasAttribute("date", $bot);
            $this->assertGreaterThanOrEqual(strtotime($bot->date), time());
        }

        $bots = $this->model->getBotsByNames($names, self::pageSize);
        $this->assertEmpty($bots);
    }

    public function testBotSubmitted()
    {
        $this->assertTrue($this->model->botSubmitted('butler_of_ec0ke'));
        $this->assertFalse($this->model->botSubmitted('freaktechnik'));
        $this->model->addSubmission('freaktechnik', 1);
        $this->assertTrue($this->model->botSubmitted('freaktechnik'));
    }

    public function testLock()
    {
        $this->assertFalse($this->model->checkRunning());
        $this->assertTrue($this->model->checkRunning());
        $this->model->checkDone();
        $this->assertFalse($this->model->checkRunning());
    }

    public function testGetLastCheckOffset()
    {
        $botCount = $this->model->getBotCount();
        $halfCount = ceil($botCount / 2);
        $this->assertEquals(0, $this->model->getLastCheckOffset($halfCount));
        $this->assertEquals($halfCount, $this->model->getLastCheckOffset(0));
        $this->assertEquals($halfCount, $this->model->getLastCheckOffset($botCount + 1));
        $this->assertEquals($halfCount + 1, $this->model->getLastCheckOffset(0));
    }

    public function testRemoveBot()
    {
        $initialCount = $this->model->getBotCount();
        $this->model->removeBot('ackbot');

        $this->assertEquals($initialCount - 1, $this->model->getBotCount());

        $queryTable = $this->getConnection()->createQueryTable(
            'bots0', "SELECT * FROM bots WHERE name='ackbot'"
        );
        $this->assertEquals(0, $queryTable->getRowCount());
    }

    public function testRemoveBots()
    {
        $initialCount = $this->model->getBotCount();
        $this->model->removeBots(array('ackbot', 'nightbot'));

        $this->assertEquals($initialCount - 2, $this->model->getBotCount());

        $queryTable = $this->getConnection()->createQueryTable(
            'bots', "SELECT * FROM bots WHERE name IN ('ackbot','nightbot')"
        );
        $this->assertEquals(0, $queryTable->getRowCount());
    }

    public function testCheckBots()
    {
        $initialCount = $this->model->getBotCount();
        $bots = $this->model->checkBots();

        $this->assertEquals($initialCount - count($bots), $this->model->getBotCount());
    }

    public function testTwitchUserExists()
    {
        $this->assertTrue($this->model->twitchUserExists('butler_of_ec0ke'));
        $this->assertTrue($this->model->twitchUserExists('xanbot'));
        $this->assertFalse($this->model->twitchUserExists('zeldbot'));
    }

    public function testGetTypes()
    {
        $bots = $this->model->getTypes();

        $queryTable = $this->getConnection()->createQueryTable(
            'bots', 'SELECT name FROM types LIMIT '.self::pageSize
        );

        $this->assertCount($queryTable->getRowCount(), $bots);

        foreach($bots as $bot) {
            $this->assertObjectHasAttribute("name", $bot);
            $this->assertObjectHasAttribute("id", $bot);
            $this->assertObjectHasAttribute("multichannel", $bot);
            $this->assertObjectHasAttribute("count", $bot);
        }

        $bots = $this->model->getTypes(2);
        $queryTable = $this->getConnection()->createQueryTable(
            'bots', 'SELECT name FROM types LIMIT '.self::pageSize.','.self::pageSize
        );
        $this->assertCount($queryTable->getRowCount(), $bots);
    }

    public function testGetCount()
    {
        $botCount = $this->model->getCount('bots');
        $this->assertEquals($this->getConnection()->getRowCount('bots'), $botCount);

        $typeCount = $this->model->getCount('types');
        $this->assertEquals($this->getConnection()->getRowCount('types'), $typeCount);

        $submissionsCount = $this->model->getCount('submissions');
        $this->assertEquals($this->getConnection()->getRowCount('submissions'), $submissionsCount);
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
     * @expectedException Exception
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
    }
}
?>
