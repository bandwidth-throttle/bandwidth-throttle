<?php

namespace malkusch\bandwidthThrottle;

use malkusch\phpmock\SleepEnvironmentBuilder;
use malkusch\phpmock\MockEnvironment;

/**
 * Test for TokenBucket.
 *
 * @author Markus Malkusch <markus@malkusch.de>
 * @link bitcoin:1335STSwu9hST4vcMRppEPgENMHD2r1REK Donations
 * @license WTFPL
 */
class TokenBucketTest extends \PHPUnit_Framework_TestCase
{
    
    /**
     * @var MockEnvironment Mock for microtime() and usleep().
     */
    private $sleepEnvironent;
    
    protected function setUp()
    {
        $builder = new SleepEnvironmentBuilder();
        $builder->setNamespace(__NAMESPACE__)
                ->setTimestamp(1417011228);

        $this->sleepEnvironent = $builder->build();
        $this->sleepEnvironent->enable();
    }
    
    protected function tearDown()
    {
        $this->sleepEnvironent->disable();
    }
    
    /**
     * Tests the intial token amount
     * 
     * @Test
     */
    public function testInitialTokens()
    {
        $tokenBucket = new TokenBucket(20, 10000);
        $this->assertEquals(20, $tokenBucket->getTokens());
    }
    
    /**
     * Tests adding tokens.
     * 
     * @Test
     */
    public function testTokenAddRate()
    {
        $microRate = 10000;
        $tokenBucket = new TokenBucket(20, $microRate);
        $tokenBucket->setTokens(0);
        
        usleep($microRate);
        $this->assertEquals(1, $tokenBucket->getTokens());
        
        usleep($microRate);
        $this->assertEquals(2, $tokenBucket->getTokens());
        
        usleep($microRate * 18);
        $this->assertEquals(20, $tokenBucket->getTokens());
        
        usleep($microRate * 2);
        $this->assertEquals(20, $tokenBucket->getTokens());
    }
    
    /**
     * Tests consuming more than the capacity.
     * 
     * @test
     * @expectedException \LengthException
     */
    public function testConsumeTooMuch()
    {
        $tokenBucket = new TokenBucket(20, 10000);
        $tokenBucket->consume(21);
    }
    
    /**
     * Tests consuming decreases token amount.
     * 
     * @param int $amount Consumed amount.
     * @param int $capacity Bucket capacity.
     * 
     * @dataProvider provideTestConsumeDecreasesTokens
     * @test
     */
    public function testConsumeDecreasesTokens($amount, $capacity)
    {
        $tokenBucket = new TokenBucket($capacity, 10000);
        $tokenBucket->consume($amount);
        
        $this->assertEquals($capacity - $amount, $tokenBucket->getTokens());
    }
    
    /**
     * Provides test cases for testConsumeDecreasesTokens().
     * 
     * @return int[][] Test cases.
     */
    public function provideTestConsumeDecreasesTokens()
    {
        return array(
            array(0, 20),
            array(1, 20),
            array(19, 20),
            array(20, 20),
        );
    }
    
    /**
     * Tests consuming several times.
     * 
     * @param int[] $amounts Consumed amounts.
     * @param int $capacity Bucket capacity.
     * 
     * @test
     * @dataProvider provideTestConsumeSubsequently
     */
    public function testConsumeSubsequently(array $amounts, $capacity)
    {
        $tokenBucket = new TokenBucket($capacity, 10000);
        foreach ($amounts as $amount) {
            $tokenBucket->consume($amount);
            
        }
    }
    
    /**
     * Returns test cases for testConsumeSubsequently().
     * 
     * @return array Test cases.
     */
    public function provideTestConsumeSubsequently()
    {
        return array(
            array(array(1, 1), 20),
            array(array(10, 5, 5), 20),
            array(array(1, 19), 20),
            array(array(19, 1), 20),
            array(array(1, 20), 20),
            array(array(1, 19, 1), 20),
            array(array(20, 1), 20),
            array(array(20, 20), 20),
        );
    }
    
    /**
     * Tests consuming which would block.
     * 
     * @param int $unblockedConsume Amount of unblocked consume.
     * @param int $blockedConsume Amount of blocked consume.
     * 
     * @test
     * @dataProvider provideTestConsumeBlocking
     */
    public function testConsumeBlocking($unblockedConsume, $blockedConsume)
    {
        $tokenBucket = new TokenBucket(20, 10000);
        $tokenBucket->consume($unblockedConsume);
        
        $time = microtime(true);
        $tokenBucket->consume($blockedConsume);
        $this->assertNotEquals(microtime(true), $time);
    }
    
    /**
     * Returns test cases for testConsumeBlocking().
     * 
     * @return int[][] Test cases.
     */
    public function provideTestConsumeBlocking()
    {
        return array(
            array(20, 1),
            array(20, 20),
            array(1, 20),
            array(19, 2),
        );
    }
    
    /**
     * Tests the Download rate.
     * 
     * @test
     * @dataProvider provideTestRate
    public function testRate(
        $tokens,
        $buffer,
        TokenBucket $bucket,
        $expectedTime
    ) {
        // clear the full bucket.
        $bucket->consume($bucket->getCapacity());
        
        $time = microtime(true);
        do {
            $chunkTokens = min($tokens, $buffer);
            $tokens -= $chunkTokens;
            $bucket->consume($chunkTokens);
            
        } while($tokens > 0);
        
        $this->assertEquals($expectedTime, microtime(true) - $time);
    }
    
    public function provideTestRate()
    {
        return array(
            array(
                1000 * 10, // 10 kB Stream
                1000, // 1 kB buffer
                new TokenBucket(1000 * 8, 1000000/1000), // 8 kB  1 kB/s
                10
            )
        );
    }
     */
    
    /**
     * Tests consuming tokens without blocking.
     * 
     * @dataProvider provideTestConsumeUnblocked
     * @test
     */
    public function testConsumeUnblocked($tokens, $capacity)
    {
         $tokenBucket = new TokenBucket($capacity, 10000);
         $time = microtime(true);
         $tokenBucket->consume($tokens);
         $this->assertEquals($time, microtime(true));
    }
    
    /**
     * Provides test cases for testConsumeUnblocked().
     * 
     * @return int[][] Test cases.
     */
    public function provideTestConsumeUnblocked()
    {
        return array(
            array(0, 20),
            array(1, 20),
            array(19, 20),
            array(20, 20),
        );
    }
    
    /**
     * Test the capacity limit of the bucket
     * 
     * @Test
     */
    public function testCapacity()
    {
        $tokenBucket = new TokenBucket(20, 10000);
        
        $tokenBucket->setTokens(21);
        $this->assertEquals(20, $tokenBucket->getTokens());
    }
    
    /**
     * Tests setToken() and getToken()
     * 
     * @Test
     * @param int $tokens Tokens
     * @dataProvider provideTestSetTokens
     */
    public function testSetAndGetTokens($tokens)
    {
        $tokenBucket = new TokenBucket($tokens + 10, 10000);

        $tokenBucket->setTokens($tokens);
        $this->assertEquals($tokens, $tokenBucket->getTokens());
    }
    
    /**
     * Test cases for testSetTokens()
     * 
     * 
     * @return int[][] tokens
     */
    public function provideTestSetTokens()
    {
        return array(
            array(0),
            array(1),
            array(2),
            array(20),
            array(200),
            array(1024),
            array(1024 * 8),
        );
    }
}
