<?php

namespace malkusch\bandwidthThrottle;

/**
 * Token Bucket algorithm.
 *
 * @author Markus Malkusch <markus@malkusch.de>
 * @link bitcoin:1335STSwu9hST4vcMRppEPgENMHD2r1REK Donations
 * @license WTFPL
 */
class TokenBucket
{

    /**
     * @var int Token add rate in microseconds.
     */
    private $microRate;
    
    /**
     * @var int Token capacity of this bucket.
     */
    private $capacity;
    
    /**
     * @var float last micro timestamp when tokens where added.
     */
    private $microTimestamp;
    
    /**
     * @var int precision scale for bc_* operations.
     */
    private $bcScale = 8;
    
    /**
     * One millisecond in microseconds.
     */
    const MILLISECOND = 1000;
    
    /**
     * One secon in microseconds.
     */
    const SECOND = 1000000;
    
    /**
     * Initializes the Token bucket.
     * 
     * @param int $capacity  Capacity of the bucket.
     * @param int $microRate Microseconds for adding one token.
     */
    public function __construct($capacity, $microRate)
    {
        $this->capacity = $capacity;
        $this->microRate = $microRate;
        $this->setTokens($capacity);
    }
    
    /**
     * Consumes tokens for the packet.
     * 
     * Consumes tokens for the packet size. If there aren't sufficient tokens
     * the method sleeps untils there are enough tokens.
     * 
     * @param int $tokens The token count.
     * @throws LengthException if packet size is larged than token capacity.
     */
    public function consume($tokens)
    {
        if ($tokens > $this->capacity) {
            throw new \LengthException("Packet size is larger than capacity.");
        
        }
        
        // Wait until tokens are refilled
        while ($this->getTokens() < $tokens) {
            // sleep, but not less than a millisecond
            $estimatedDuration = ($tokens - $this->getTokens()) * $this->microRate;
            usleep(max($estimatedDuration, self::MILLISECOND));
            
        }
        
        $this->setTokens($this->getTokens() - $tokens);
    }
    
    /**
     * Sets the amount of tokens.
     * 
     * @param int $tokens The amount of tokens.
     */
    public function setTokens($tokens)
    {
        $duration = $tokens * $this->microRate / self::SECOND;
        $time = microtime(true);
        
        // $error = ($time - $this->microTimestamp) * self::SECOND % $this->microRate / self::SECOND;
        $error = 0;
        
        $this->microTimestamp = $time - $duration - $error;
    }
    
    /**
     * Returns the tokens.
     * 
     * @return int The tokens.
     */
    public function getTokens()
    {
        $diff = bcsub(microtime(true), $this->microTimestamp, $this->bcScale);
        $tokens = (int) ($diff * self::SECOND / $this->microRate);
        return min($this->capacity, $tokens);
    }
    
    /**
     * The token capacity of this bucket.
     *
     * @return int The capacity.
     */
    public function getCapacity()
    {
        return $this->capacity;
    }
}
