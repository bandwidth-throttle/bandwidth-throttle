<?php

namespace bandwidthThrottle;

use bandwidthThrottle\tokenBucket\TokenBucketBuilder;

/**
 * Stream based bandwidth throtteling.
 *
 * This class is a facade for the throtteling stream filter
 * {@link TokenBucketFilter} and PHP's stream_filter_* functions.
 *
 * You have to set a rate with any of the setRate* methods. Then you can
 * throttle a stream by calling the {@link throttle()} method.
 * After that all operations on that stream are throttled.
 *
 * Per default the throttle applies for both, input and output streams.
 *
 * The following example will stream a video with a rate of 100KiB/s to the
 * browser:
 * <code>
 * use bandwidthThrottle\BandwidthThrottle;
 *
 * $in  = fopen(__DIR__ . "/resources/video.mpg", "r");
 * $out = fopen("php://output", "w");
 *
 * $throttle = new BandwidthThrottle();
 * $throttle->setRateInKiBperSecond(100); // Set limit to 100KiB/s
 * $throttle->throttle($out);
 *
 * stream_copy_to_stream($in, $out);
 * </code>
 *
 * @author Markus Malkusch <markus@malkusch.de>
 * @link bitcoin:1335STSwu9hST4vcMRppEPgENMHD2r1REK Donations
 * @license WTFPL
 */
class BandwidthThrottle
{

    /**
     * @var TokenBucketBuilder The token bucket builder.
     */
    private $tokenBucketBuilder;
    
    /**
     * @var int The read_write filter mode.
     */
    private $filterMode = STREAM_FILTER_ALL;
    
    /**
     * @var resource The throttle filter.
     */
    private $filter;
    
    /**
     * @var bool If the filter is registered.
     */
    private static $registered = false;

    /**
     * The registered filter name.
     * @internal
     */
    const FILTER_NAME = "bandwidthThrottle";

    public function __construct()
    {
        $this->tokenBucketBuilder = new TokenBucketBuilder();
    }

    /**
     * Set the rate in bytes per second.
     *
     * @param int $bytes Bytes per second rate.
     */
    public function setRateInBytesPerSecond($bytes)
    {
        $this->tokenBucketBuilder->setRateInBytesPerSecond($bytes);
    }
    
    /**
     * Set the rate in kibibytes per second.
     *
     * @param int $kibibytes Kibibytes per second rate.
     */
    public function setRateInKiBperSecond($kibibytes)
    {
        $this->tokenBucketBuilder->setRateInKiBperSecond($kibibytes);
    }
    
    /**
     * Set the rate in mebibytes per second.
     *
     * @param int $mebibytes mebibytes per second rate.
     */
    public function setRateInMiBPerSecond($mebibytes)
    {
        $this->tokenBucketBuilder->setRateInMiBPerSecond($mebibytes);
    }
    
    /**
     * Throttles only the input stream.
     *
     * Default is throtteling both streams.
     */
    public function setThrottleInputStream()
    {
        $this->filterMode = STREAM_FILTER_READ;
    }

    /**
     * Throttles only the output stream.
     *
     * Default is throtteling both streams.
     */
    public function setThrottleOutputStream()
    {
        $this->filterMode = STREAM_FILTER_WRITE;
    }

    /**
     * Throttles the output and input stream.
     *
     * This is the default mode.
     */
    public function setThrottleBothStreams()
    {
        $this->filterMode = STREAM_FILTER_ALL;
    }
    
    /**
     * Throttles a stream to the given rate.
     *
     * This registers a filter to the given stream which does the traffic
     * shaping. After that any stream operation is throttled.
     *
     * The stream can be an input or an output stream.
     *
     * This object can throttle only one stream at a time. If you want to
     * call throttle() again, make either sure you called {@link unthrottle()}
     * before or use a new instance.
     *
     * @param resource $stream The stream.
     * @throws BandwidthThrottleException Error during throtteling the stream.
     */
    public function throttle($stream)
    {
        if (is_resource($this->filter)) {
            throw new BandwidthThrottleException(
                "This throttle is still attached to a stream. Call unthrottle() or use a new instance."
            );
        }
        $this->registerOnce();

        $this->filter = stream_filter_append(
            $stream,
            self::FILTER_NAME,
            $this->filterMode,
            $this->tokenBucketBuilder->build()
        );
        if (!is_resource($this->filter)) {
            throw new BandwidthThrottleException("Could not throttle the stream.");
        }
    }
    
    /**
     * Registers the filter once for all instances.
     *
     * If the filter was already registered the method returns silently.
     *
     * @throws BandwidthThrottleException Registration failed.
     */
    private function registerOnce()
    {
        if (self::$registered) {
            return;

        }
        if (!stream_filter_register(self::FILTER_NAME, "bandwidthThrottle\\TokenBucketFilter")) {
            throw new BandwidthThrottleException("Could not register throttle filter.");

        }
        self::$registered = true;
    }
    
    /**
     * Unthrottles a previously throttled stream.
     *
     * If the throttle was not applied to a stream, this method returns silenty.
     *
     * @throws BandwidthThrottleException The throttle could not be removed.
     */
    public function unthrottle()
    {
        if (!is_resource($this->filter)) {
            return;

        }
        if (!stream_filter_remove($this->filter)) {
            throw new BandwidthThrottleException("Failed to unthrottle stream.");

        }
        unset($this->filter);
    }
}
