<?php

require_once "DBTestCase.php";

use \Mini\Model\PingablePDO;

/**
 * @coversDefaultClass \Mini\Model\Types
 */
class TypesTest extends DBTestCase
{
    /**
     * @var \Mini\Model\Types
     */
    private $types;

    /**
     * @var int
     */
    const pageSize = 100;

    public function setUp()
    {
        $this->types = new \Mini\Model\Types(self::$pdo, self::pageSize);
        parent::setUp();
    }

    public function tearDown()
    {
        $this->types = null;
        parent::tearDown();
    }

    /**
     * @covers ::getTypes
     */
    public function testGetTypes()
    {
        $bots = $this->types->getTypes();

        $queryTable = $this->getConnection()->createQueryTable(
            'bots', 'SELECT name FROM types LIMIT '.self::pageSize
        );

        $this->assertCount($queryTable->getRowCount(), $bots);

        foreach($bots as $bot) {
            $this->assertObjectHasAttribute("name", $bot);
            $this->assertObjectHasAttribute("id", $bot);
            $this->assertObjectHasAttribute("multichannel", $bot);
            $this->assertObjectHasAttribute("count", $bot);
            $this->assertInstanceOf(\Mini\Model\Type::class, $bot);
        }

        $bots = $this->types->getTypes(2);
        $queryTable = $this->getConnection()->createQueryTable(
            'bots', 'SELECT name FROM types LIMIT '.self::pageSize.','.self::pageSize
        );
        $this->assertCount($queryTable->getRowCount(), $bots);
    }

    /**
     * @covers ::getType
     */
    public function testGetType()
    {
        $type = $this->types->getType(1);

        $this->assertEquals("Nightbot", $type->name);
        $this->assertEquals(1, $type->id);
        $this->assertEquals(true, $type->multichannel);
        $this->assertEquals("https://www.nightbot.tv/", $type->url);
        $this->assertInstanceOf(\Mini\Model\Type::class, $type);
    }

    /**
     * @covers ::getType
     */
    public function testGetNotExistingType()
    {
        $type = $this->types->getType(0);

        $this->assertFalse($type);
    }

    /**
     * @covers ::getAllTypes
     */
    public function testGetAllTypes()
    {
        $types = $this->types->getAllTypes();

        $this->assertCount($this->getConnection()->getRowCount('types'), $types);

        foreach($types as $type) {
            $this->assertObjectHasAttribute("name", $type);
            $this->assertObjectHasAttribute("id", $type);
            $this->assertObjectHasAttribute("url", $type);
            $this->assertObjectHasAttribute("multichannel", $type);
            $this->assertObjectHasAttribute("date", $type);
            $this->assertGreaterThanOrEqual(strtotime($type->date), time());
            $this->assertInstanceOf(\Mini\Model\Type::class, $type);
        }
    }
}
