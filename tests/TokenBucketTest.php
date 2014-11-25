<?php

namespace malkusch\bandwithThrottle;

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
     * Tests the intial token amount
     * 
     * @Test
     */
    public function testInitialTokens()
    {
        $tokenBucket = new TokenBucket(20, 10000);
        $this->assertEquals(20, $tokenBucket->getTokens());
    }
}
