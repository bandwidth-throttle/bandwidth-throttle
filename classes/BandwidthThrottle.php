<?php

namespace bandwidthThrottle;

use bandwidthThrottle\tokenBucket\TokenBucket;
use bandwidthThrottle\tokenBucket\Rate;
use bandwidthThrottle\tokenBucket\storage\Storage;
use bandwidthThrottle\tokenBucket\storage\StorageException;
use bandwidthThrottle\tokenBucket\storage\SingleProcessStorage;

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
 * If you do use one stream bidirectional (which you probably don't) the
 * effective rate might not be what you expect. both directions will share
 * the same throttle, and therefore in total share the bandwidth. E.g. if you
 * limit to 100KiB/s you could read and write each with 50KiB/s. If this is not
 * what you want consider using dedicated throttles and streams.
 *
 * The following example will stream a video with a rate of 100KiB/s to the
 * client:
 * <code>
 * use bandwidthThrottle\BandwidthThrottle;
 *
 * $in  = fopen(__DIR__ . "/video.mpg", "r");
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
final class BandwidthThrottle
{

    /**
     * Unit for bytes.
     */
    const BYTES = "bytes";

    /**
     * Unit for kilobytes (1000 bytes).
     */
    const KILOBYTES = "kilobytes";

    /**
     * Unit for kibibytes (1024 bytes).
     */
    const KIBIBYTES = "kibibytes";

    /**
     * Unit for megabytes (1000 kilobytes).
     */
    const MEGABYTES = "megabytes";

    /**
     * Unit for mebibytes (1024 kibibytes).
     */
    const MEBIBYTES = "mebibytes";
    
    /**
     * @var int[] The unit map.
     */
    private static $unitMap = [
        self::BYTES     => 1,
        self::KILOBYTES => 1000,
        self::KIBIBYTES => 1024,
        self::MEGABYTES => 1000000,
        self::MEBIBYTES => 1048576,
    ];

    /**
     * @var Rate The rate.
     */
    private $rate;
    
    /**
     * @var int|null The capacity.
     */
    private $capacity;
    
    /**
     * @var int The initial amount of tokens.
     */
    private $initialTokens = 0;
    
    /**
     * @var Storage The token bucket storage.
     */
    private $storage;
    
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

    /**
     * Initialization.
     */
    public function __construct()
    {
        $this->storage = new SingleProcessStorage();
    }

    /**
     * Sets the storage.
     *
     * The storage determines the scope of the throttle. Setting the storage is
     * optional. The default storage is limited to the request scope.
     * I.e. it will throttle the bandwidth per request.
     *
     * @param Storage $storage The storage.
     */
    public function setStorage(Storage $storage)
    {
        $this->storage = $storage;
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
        $this->rate = new Rate($this->convertToBytes($rate, $unit), Rate::SECOND);
    }

    /**
     * Converts an amount of an unit into the amount of bytes.
     *
     * @param int    $amount The amount of the unit.
     * @param string $unit   The unit.
     *
     * @return int The amount in bytes.
     * @throws \InvalidArgumentException The unit was invalid.
     */
    private function convertToBytes($amount, $unit)
    {
        if (!isset(self::$unitMap[$unit])) {
            throw new \InvalidArgumentException("The unit was invalid.");
        }
        return $amount * self::$unitMap[$unit];
    }
    
    /**
     * Sets the burst capacity.
     *
     * Setting the burst capacity is optional. If no capacity was set, the
     * capacity is set to the amount of bytes for one second.
     *
     * @param int    $capacity The burst capacity.
     * @param string $unit     The unit for the capacity, default is bytes.
     *
     * @throws \InvalidArgumentException The unit was invalid.
     */
    public function setBurstCapacity($capacity, $unit = self::BYTES)
    {
        $this->capacity = $this->convertToBytes($capacity, $unit);
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
        $this->initialTokens = $this->convertToBytes($initialBurst, $unit);
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
     *
     * @throws BandwidthThrottleException Error during throtteling the stream.
     * @throws \LengthException The initial burst size was greater than the burst size.
     */
    public function throttle($stream)
    {
        try {
            if (is_resource($this->filter)) {
                throw new BandwidthThrottleException(
                    "This throttle is still attached to a stream. Call unthrottle() or use a new instance."
                );
            }
            $this->registerOnce();

            $capacity = empty($this->capacity)
                ? $this->rate->getTokensPerSecond()
                : $this->capacity;

            $bucket = new TokenBucket($capacity, $this->rate, $this->storage);
            $bucket->bootstrap($this->initialTokens);

            $this->filter = stream_filter_append(
                $stream,
                self::FILTER_NAME,
                $this->filterMode,
                $bucket
            );
            if (!is_resource($this->filter)) {
                throw new BandwidthThrottleException("Could not throttle the stream.");
            }
        } catch (StorageException $e) {
            throw new BandwidthThrottleException("Could not initialize token bucket.", 0, $e);
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
