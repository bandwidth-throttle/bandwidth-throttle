<?php

namespace bandwidthThrottle;

use bandwidthThrottle\tokenBucket\TokenBucket;

/**
 * Stream filter which uses a token bucket for traffic shaping.
 *
 * When the filter is created with stream_filter_append() or
 * stream_filter_prepend(), the $param parameter is expected to be an
 * instance of TokenBucket.
 * 
 * @author Markus Malkusch <markus@malkusch.de>
 * @link bitcoin:1335STSwu9hST4vcMRppEPgENMHD2r1REK Donations
 * @license WTFPL
 */
class TokenBucketFilter extends \php_user_filter
{

    /**
     * @var TokenBucket The token bucket.
     */
    private $tokenBucket;
    
    /**
     * Build the token bucket.
     * 
     * @throws \InvalidArgumentException The token bucket was not passed in the $params parameter.
     */
    public function onCreate()
    {
        if (!$this->params instanceof TokenBucket) {
            throw new \InvalidArgumentException(
                "An instance of TokenBucket must be passed as \$params parameter."
            );
        }
        $this->tokenBucket = $this->params;
    }
    
    /**
     * Traffic shaping.
     *
     * @param resource $in       The input stream.
     * @param resource $out      The ouput stream.
     * @param int      $consumed The amount of consumed bytes.
     * @param bool     $closing  If the stream is closing.
     *
     * @return int The processing state.
     */
    public function filter($in, $out, &$consumed, $closing)
    {
        while ($bucket = stream_bucket_make_writeable($in)) {
            $chunks = str_split($bucket->data, $this->tokenBucket->getCapacity());
            foreach ($chunks as $chunk) {
                $tokens = strlen($chunk);
                $this->tokenBucket->consume($tokens);
                $consumed += $tokens;
                
            }
            stream_bucket_append($out, $bucket);
        }
        return PSFS_PASS_ON;
    }
}
