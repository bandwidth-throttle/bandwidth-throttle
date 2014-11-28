<?php

namespace malkusch\bandwidthThrottle;

/**
 * Token Bucket builder.
 *
 * @author Markus Malkusch <markus@malkusch.de>
 * @link bitcoin:1335STSwu9hST4vcMRppEPgENMHD2r1REK Donations
 * @license WTFPL
 */
class TokenBucketBuilder
{
    
    /**
     * @var int Token add rate in microseconds.
     */
    private $microRate;
    
    /**
     * @var int Token capacity in bytes.
     */
    private $capacity;
    
    /**
     * Set the rate in bytes per second.
     * 
     * @param int $bytes Bytes per second rate.
     */
    public function setRateInBytesPerSecond($bytes)
    {
        $this->microRate = TokenBucket::SECOND / $bytes;
    }
    
    /**
     * Set the rate in kilobytes per second.
     * 
     * @param int $kbytes Kilobytes per second rate.
     */
    public function setRateInKilobytesPerSecond($kbytes)
    {
        $this->setRateInBytesPerSecond($kbytes * 1024);
    }
    
    /**
     * Sets the capacity in bytes.
     * 
     * @param int $bytes The capacity in bytes.
     */
    public function setCapacityInBytes($bytes)
    {
        $this->capacity = $bytes;
    }
    
    /**
     * Sets the capacity in kilobytes.
     * 
     * @param int $kbytes The capacity in kilobytes.
     */
    public function setCapacityInKBytes($kbytes)
    {
        $this->setCapacityInBytes($kbytes * 1024);
    }
    
    /**
     * Sets the capacity in megabytes.
     * 
     * @param int $mbytes The capacity in megabytes.
     */
    public function setCapacityInMBytes($mbytes)
    {
        $this->setCapacityInKBytes($mbytes * 1024);
    }
    
    /**
     * Builds the Token Bucket.
     * 
     * @return TokenBucket The Token Bucket
     */
    public function build()
    {
        return new TokenBucket($this->capacity, $this->microRate);
    }
}
