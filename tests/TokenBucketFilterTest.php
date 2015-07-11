<?php

namespace bandwidthThrottle;

use bandwidthThrottle\tokenBucket\storage\SingleProcessStorage;
use bandwidthThrottle\tokenBucket\Rate;
use bandwidthThrottle\tokenBucket\TokenBucket;
use phpmock\environment\SleepEnvironmentBuilder;
use phpmock\environment\MockEnvironment;

/**
 * Test for TokenBucketFilter.
 *
 * @author Markus Malkusch <markus@malkusch.de>
 * @link bitcoin:1335STSwu9hST4vcMRppEPgENMHD2r1REK Donations
 * @license WTFPL
 * @see TokenBucketFilter
 */
class TokenBucketFilterTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @var MockEnvironment Mock for microtime() and usleep().
     */
    private $sleepEnvironent;
    
    /**
     * @var resource The SUT.
     */
    private $filter;
    
    protected function setUp()
    {
        $builder = new SleepEnvironmentBuilder();
        $builder->addNamespace(__NAMESPACE__)
                ->addNamespace("bandwidthThrottle\\tokenBucket")
                ->addNamespace("bandwidthThrottle\\tokenBucket\\converter")
                ->setTimestamp(1417011228);

        $this->sleepEnvironent = $builder->build();
        $this->sleepEnvironent->enable();
    }
    
    protected function tearDown()
    {
        $this->sleepEnvironent->disable();
        if (is_resource($this->filter)) {
            stream_filter_remove($this->filter);
        }
    }
    
    /**
     * Tests creating filter fails if no TokenBucket was passed.
     *
     * @test
     * @expectedException \InvalidArgumentException
     */
    public function testOnCreatesFails()
    {
        stream_filter_register("testOnCreatesFails", "bandwidthThrottle\\TokenBucketFilter");
        $this->filter = stream_filter_append(fopen("php://memory", "w"), "testOnCreatesFails");
    }
    
    /**
     * Traffic shaping doesn't alter the content.
     *
     * @param string[] $writes The writes to the stream.
     *
     * @test
     * @dataProvider provideTestFilterConservesContent
     */
    public function testFilterConservesContent(array $writes)
    {
        $stream = fopen("php://memory", "rw");
        $bucket = new TokenBucket(10, new Rate(1, Rate::SECOND), new SingleProcessStorage());
        
        stream_filter_register("test", "bandwidthThrottle\\TokenBucketFilter");
        $this->filter = stream_filter_append($stream, "test", STREAM_FILTER_WRITE, $bucket);
        
        foreach ($writes as $write) {
            fwrite($stream, $write);
        }

        fseek($stream, 0);
        $content = stream_get_contents($stream);

        $this->assertEquals(implode("", $writes), $content);
    }
    
    /**
     * Returns test cases for testFilterConservesContent().
     *
     * @return array Test cases.
     */
    public function provideTestFilterConservesContent()
    {
        return [
            [["a"]],
            [["a", "b"]],
            [["ab"]],
            [["ab", "c"]],
            [["123456789", "0"]],
            [["123456789", "0a"]],
            [["1234567890", "ab"]],
            [["1234567890a"]],
            [["1234567890a", "b"]],
        ];
    }
    
    /**
     * Tests traffic shapping filtering.
     *
     * @param float  $expectedDuration The expected duration in seconds.
     * @param int[]  $bytes            The amount of bytes to write.
     *
     * @test
     * @dataProvider provideTestFilterShapesTraffic
     */
    public function testFilterShapesTraffic($expectedDuration, array $bytes)
    {
        $stream = fopen("php://memory", "w");
        $bucket = new TokenBucket(10, new Rate(1, Rate::SECOND), new SingleProcessStorage());
        
        stream_filter_register("test", "bandwidthThrottle\\TokenBucketFilter");
        $this->filter = stream_filter_append($stream, "test", null, $bucket);
        
        $time = microtime(true);
        foreach ($bytes as $byte) {
            fwrite($stream, str_repeat(" ", $byte));
        }
        $this->assertLessThan(1e-3, abs((microtime(true) - $time) - $expectedDuration));
    }
    
    /**
     * Returns test cases for testFilterShapesTraffic().
     *
     * @return array The test cases.
     */
    public function provideTestFilterShapesTraffic()
    {
        return [
            [1,  [1]],
            [2,  [2]],
            [10, [10]],
            [11, [11]],
            [20, [20]],
            [2,  [1, 1]],
            [10, [1, 9]],
            [11, [2, 9]],
            [12, [2, 10]],
            [22, [2, 20]],
            [20, [10, 10]],
            [42, [21, 21]],
            [512, [512]],
            [1024, [1024]],
        ];
    }
}
