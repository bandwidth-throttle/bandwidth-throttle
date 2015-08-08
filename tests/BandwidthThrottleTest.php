<?php

namespace bandwidthThrottle;

/**
 * Test for BandwidthThrottle.
 *
 * @author Markus Malkusch <markus@malkusch.de>
 * @link bitcoin:1335STSwu9hST4vcMRppEPgENMHD2r1REK Donations
 * @license WTFPL
 * @see BandwidthThrottle
 */
class BandwidthThrottleTest extends \PHPUnit_Framework_TestCase
{

    /**
     * Tests the resulting bandwidth.
     *
     * @test
     */
    public function testRate()
    {
        $this->markTestIncomplete();
    }
    
    /**
     * Tests the burst capacity can be consumed instantly.
     *
     * @test
     */
    public function testBurstCapacity()
    {
        $this->markTestIncomplete();
    }
    
    /**
     * Tests the default burst capacity.
     *
     * @test
     */
    public function testDefaultBurstCapacity()
    {
        $this->markTestIncomplete();
    }
    
    /**
     * Tests the initial burst can be consumed instantly.
     *
     * @test
     */
    public function testInitialBurst()
    {
        $this->markTestIncomplete();
    }
    
    /**
     * Tests throtteling the input stream only.
     *
     * @test
     */
    public function testThrottleInputStream()
    {
        $this->markTestIncomplete();
    }
    
    /**
     * Tests throtteling the output stream only.
     *
     * @test
     */
    public function testThrottleOutputStream()
    {
        $this->markTestIncomplete();
    }
    
    /**
     * Tests throtteling the input and output streams.
     *
     * @test
     */
    public function testThrottleBothStreams()
    {
        $this->markTestIncomplete();
    }
    
    /**
     * Tests throttle() throttles a stream.
     *
     * @test
     */
    public function testThrottle()
    {
        $this->markTestIncomplete();
    }
    
    /**
     * Tests unthrottle() unthrottles a stream.
     *
     * @test
     */
    public function testUnthrottle()
    {
        $this->markTestIncomplete();
    }
    
    /**
     * Tests throttle on separate streams.
     *
     * @test
     */
    public function testThrottleMoreStreams()
    {
        $this->markTestIncomplete();
    }
    
    /**
     * Tests multiple throttles on one stream.
     *
     * @test
     */
    public function testMultipleThrottlesOnOneStream()
    {
        $this->markTestIncomplete();
    }
}
