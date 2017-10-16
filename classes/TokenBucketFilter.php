<?php

namespace bandwidthThrottle;

use bandwidthThrottle\tokenBucket\BlockingConsumer;
use bandwidthThrottle\tokenBucket\TokenBucket;
use bandwidthThrottle\tokenBucket\storage\StorageException;

/**
 * Stream filter which uses a token bucket for traffic shaping.
 *
 * When the filter is created with stream_filter_append() or
 * stream_filter_prepend(), the $param parameter is expected to be an
 * instance of TokenBucket.
 *
 * This filter can shape traffic in both directions. I.e. you can append it
 * to an output stream as well to an input stream.
 *
 * Example:
 * <code>
 * use bandwidthThrottle\TokenBucketFilter;
 * use bandwidthThrottle\tokenBucket\TokenBucket;
 * use bandwidthThrottle\tokenBucket\Rate;
 * use bandwidthThrottle\tokenBucket\storage\SingleProcessStorage;
 *
 * $in  = fopen(__DIR__ . "/video.mpg", "r");
 * $out = fopen("php://output", "w");
 *
 * $storage = new SingleProcessStorage();
 * $rate    = new Rate(100 * 1024, Rate::SECOND); // Rate of 100KiB/s
 * $bucket  = new TokenBucket(100 * 1024, $rate, $storage);
 *
 * stream_filter_register("throttle", TokenBucketFilter::class);
 * stream_filter_append($out, "throttle", STREAM_FILTER_WRITE, $bucket);
 *
 * stream_copy_to_stream($in, $out);
 * </code>
 *
 * @author Markus Malkusch <markus@malkusch.de>
 * @link bitcoin:1335STSwu9hST4vcMRppEPgENMHD2r1REK Donations
 * @license WTFPL
 */
final class TokenBucketFilter extends \php_user_filter
{

    /**
     * @var BlockingConsumer The blocking token bucket consumer.
     */
    private $tokenConsumer;
    
    /**
     * @var TokenBucket  The token bucket.
     */
    private $tokenBucket;
    
    /**
     * Builds the token bucket consumer.
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
        $this->tokenBucket   = $this->params;
        $this->tokenConsumer = new BlockingConsumer($this->tokenBucket);
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
     *
     * @SuppressWarnings("unused")
     * @SuppressWarnings("short")
     */
    public function filter($in, $out, &$consumed, $closing)
    {
        try {
            while ($bucket = stream_bucket_make_writeable($in)) {
                $chunks = str_split($bucket->data, $this->tokenBucket->getCapacity());
                foreach ($chunks as $chunk) {
                    $tokens = strlen($chunk);
                    $this->tokenConsumer->consume($tokens);
                    $consumed += $tokens;
                }
                stream_bucket_append($out, $bucket);
            }
            return PSFS_PASS_ON;
        } catch (StorageException $e) {
            trigger_error($e->getMessage(), E_USER_ERROR);
            return PSFS_ERR_FATAL;
        } catch (\LengthException $e) {
            /*
             * This case would be a logic error, as the stream chunk is already
             * splitted to the bucket's capacity.
             */
            trigger_error($e->getMessage(), E_USER_ERROR);
            return PSFS_ERR_FATAL;
        }
    }
}
