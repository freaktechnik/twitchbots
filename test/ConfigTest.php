<?php

require_once "DBTestCase.php";

use \Mini\Model\Config;

/**
 * @coversDefaultClass \Mini\Model\Config
 */
class ConfigTest extends DBTestCase
{
    static protected $tables = ['config'];
    static protected $dataSet = 'pdostorage';

    /**
     * @var \Mini\Model\Config $config
     */
    private $config;

    public function setUp()
    {
        $this->config = new Config(self::$pdo);

        parent::setUp();
    }

    public function tearDown()
    {
        $this->config = null;

        parent::tearDown();
    }

    /**
     * @covers ::get
     * @uses \Mini\Model\Store::prepareSelect
     */
    public function testGet()
    {
        $value = $this->config->get('1_get');

        $this->assertEquals('success', $value);
    }

    /**
     * @covers ::set
     * @uses \Mini\Model\Config::get
     * @uses \Mini\Model\Store::prepareUpdate
     */
    public function testSet()
    {
        $key = '1_has';
        $this->config->set($key, 'no');

        $newValue = $this->config->get($key);

        $this->assertEquals('no', $newValue);
    }
}
