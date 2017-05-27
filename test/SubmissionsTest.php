<?php

use \Mini\Model\{PingablePDO, Submissions};
/**
 * @coversDefaultClass \Mini\Model\Submissions
 */
class SubmissionsTest extends DBTestCase
{
    /**
     * @var \Mini\Model\Submissions
     */
    private $submissions;

    /**
     * @var int
     */
    const pageSize = 100;

    public function setUp()
    {
        $this->submissions = new Submissions(self::$pdo, self::pageSize);
        parent::setUp();
    }

    public function tearDown()
    {
        $this->submissions = null;
        parent::tearDown();
    }

    /**
     * @covers ::getSubmissions
     */
    public function testGetSubmissions()
    {
        $this->assertEquals(count($this->submissions->getSubmissions()), $this->getConnection()->getRowCount('submissions'), "Not an empty array with no submissions");

        $this->submissions->append(1, "test", "lorem ipsum", Submissions::SUBMISSION);
        $this->submissions->append(2, "nightboot", "1", Submissions::CORRECTION);
        $this->assertEquals(2, $this->getConnection()->getRowCount('submissions'), "Test setup failed");

        $submissions = $this->submissions->getSubmissions();

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
}
