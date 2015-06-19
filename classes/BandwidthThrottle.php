<?php

namespace bandwidthThrottle;

use bandwidthThrottle\tokenBucket\TokenBucketBuilder;

/**
 * Stream based bandwidth throtteling.
 *
 * This class is a facade for the throtteling stream filter
 * {@link TokenBucketFilter} and PHP's stream_filter_* functions.
 *
 * You have to set a rate with {@link setRate()}. Then you can
 * throttle a stream by calling the {@link throttle()} method.
 * After that all operations on that stream are throttled.
 *
 * Per default the throttle applies for both, input and output streams.
 *
 * The following example will stream a video with a rate of 100KiB/s to the
 * client:
 * <code>
 * use bandwidthThrottle\BandwidthThrottle;
 *
 * $in  = fopen(__DIR__ . "/resources/video.mpg", "r");
 * $out = fopen("php://output", "w");
 *
 * $throttle = new BandwidthThrottle();
 * $throttle->setRate(100, BandwidthThrottle::KIBIBYTES); // Set limit to 100KiB/s
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
     * Unit for bytes.
     */
    const BYTES = TokenBucketBuilder::BYTES;

    /**
     * Unit for kilobytes (1000 bytes).
     */
    const KILOBYTES = TokenBucketBuilder::KILOBYTES;

    /**
     * Unit for kibibytes (1024 bytes).
     */
    const KIBIBYTES = TokenBucketBuilder::KIBIBYTES;

    /**
     * Unit for megabytes (1000 kilobytes).
     */
    const MEGABYTES = TokenBucketBuilder::MEGABYTES;

    /**
     * Unit for mebibytes (1024 kibibytes).
     */
    const MEBIBYTES = TokenBucketBuilder::MEBIBYTES;

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
     * Sets the rate per second.
     *
     * @param int    $rate The rate per second.
     * @param string $unit The unit for the rate, default is bytes.
     *
     * @throws \InvalidArgumentException The unit was invalid.
     */
    public function setRate($rate, $unit = self::BYTES)
    {
        $this->tokenBucketBuilder->setRate($rate, $unit);
    }

    /**
     * Sets the burst capacity.
     *
     * Setting the burst capacity is optional. If no capacity was set, the
     * capacity is set to the rate.
     *
     * @param int    $capacity The burst capacity.
     * @param string $unit     The unit for the capacity, default is bytes.
     *
     * @throws \InvalidArgumentException The unit was invalid.
     */
    public function setBurstCapacity($capacity, $unit = self::BYTES)
    {
        $this->tokenBucketBuilder->setCapacity($capacity, $unit);
    }
    
    /**
     * Sets the initial burst size.
     *
     * This size determines how many bytes can be send instantly after the
     * throttle was activated without limiting the rate.
     *
     * Setting this size is optional. Default is 0.
     *
     * @param int    $initialBurst The initial burst size.
     * @param string $unit         The unit for the burst size, default is bytes.
     *
     * @throws \InvalidArgumentException The unit was invalid.
     */
    public function setInitialBurst($initialBurst, $unit = self::BYTES)
    {
        $this->tokenBucketBuilder->setInitialTokens($initialBurst, $unit);
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
