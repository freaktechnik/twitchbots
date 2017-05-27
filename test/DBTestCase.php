<?php

include_once('_fixtures/setup.php');

use PHPUnit\Framework\TestCase;
use PHPUnit\DbUnit\TestCaseTrait;

class DBTestCase extends TestCase {
    use TestCaseTrait;

    // Database connection efficieny
    static protected $pdo = null;
    static protected $configOnly = false;
    static protected $dataSet = 'bots';
    private $conn = null;

    public static function setUpBeforeClass()
    {
        self::$pdo = create_pdo($GLOBALS);
        if(self::$configOnly) {
            create_config_table(self::$pdo);
        }
        else {
            create_tables();
        }

        parent::setUpBeforeClass();
    }

    public static function tearDownAfterClass()
    {
        self::$pdo = null;

        parent::tearDownAfterClass();
    }

    public function getConnection(): PHPUnit\DbUnit\Database\DefaultConnection
    {
        if ($this->conn === null) {
            $this->conn = $this->createDefaultDBConnection(self::$pdo->getOriginalPDO(), ':memory:');
        }

        return $this->conn;
    }

    public function getDataSet(): PHPUnit\DbUnit\DataSet\XmlDataSet
    {
        return $this->createXMLDataSet(dirname(__FILE__).'/_fixtures/'.self::$dataSet.'.xml');
    }
}
