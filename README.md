# Bandwidth Throttle

This library implements traffic shaping on streams (input and output streams).

# Installation

Use [Composer](https://getcomposer.org/):

```sh
composer require bandwidth-throttle/bandwidth-throttle
```

# Usage

The package is in the namespace
[`bandwidthThrottle`](http://bandwidth-throttle.github.io/bandwidth-throttle/api/namespace-bandwidthThrottle.html).

[`BandwidthThrottle::setRate()`](http://bandwidth-throttle.github.io/bandwidth-throttle/api/class-bandwidthThrottle.BandwidthThrottle.html#_setRate)
sets the bandwidth limit. E.g. this would set it to 100KiB/s:
```php
$throttle->setRate(100, BandwidthThrottle::KIBIBYTES)
```

[`BandwidthThrottle::throttle()`](http://bandwidth-throttle.github.io/bandwidth-throttle/api/class-bandwidthThrottle.BandwidthThrottle.html#_throttle)
throttles a stream. After that any stream operation (e.g. `fread()`) will be
limited to the throttle rate.

## Optional methods

[`BandwidthThrottle::setBurstCapacity()`](http://bandwidth-throttle.github.io/bandwidth-throttle/api/class-bandwidthThrottle.BandwidthThrottle.html#_setBurstCapacity)
sets the burst capacity. This is the capacity which can be accumulated while
the stream is not in use. Accumulated capacity can be consumed instantly. Per
default this the amount of bytes for one second from the rate.

[`BandwidthThrottle::setInitialBurst()`](http://bandwidth-throttle.github.io/bandwidth-throttle/api/class-bandwidthThrottle.BandwidthThrottle.html#_setInitialBurst)
sets the initial burst. Per default the throttle starts with 0 accumulated
bytes. Setting an initial burst makes that amount of bytes instantly available.

[`BandwidthThrottle::setStorage()`](http://bandwidth-throttle.github.io/bandwidth-throttle/api/class-bandwidthThrottle.BandwidthThrottle.html#_setStorage)
sets the storage for the underlying [token bucket](https://github.com/bandwidth-throttle/token-bucket).
The storage determines the scope of the bucket. The default storage is in
the request scope. I.e. it will limit the rate per request. There are
[storages](http://bandwidth-throttle.github.io/token-bucket/api/class-bandwidthThrottle.tokenBucket.storage.Storage.html)
which can be shared amongst requests.

[`BandwidthThrottle::setThrottleBothStreams()`](http://bandwidth-throttle.github.io/bandwidth-throttle/api/class-bandwidthThrottle.BandwidthThrottle.html#_setThrottleBothStreams)
will apply the throttle for both input and output streams. This is the default
mode.

[`BandwidthThrottle::setThrottleInputStream()`](http://bandwidth-throttle.github.io/bandwidth-throttle/api/class-bandwidthThrottle.BandwidthThrottle.html#_setThrottleInputStream)
will apply the throttle for the input stream only.

[`BandwidthThrottle::setThrottleOutputStream()`](http://bandwidth-throttle.github.io/bandwidth-throttle/api/class-bandwidthThrottle.BandwidthThrottle.html#_setThrottleOutputStream)
will apply the throttle for the output stream only.

[`BandwidthThrottle::unthrottle()`](http://bandwidth-throttle.github.io/bandwidth-throttle/api/class-bandwidthThrottle.BandwidthThrottle.html#_unthrottle)
removes the throttle from the stream.

## Example

This example will stream a video with a rate of 100KiB/s to the browser:

```php
use bandwidthThrottle\BandwidthThrottle;

$in  = fopen(__DIR__ . "/resources/video.mpg", "r");
$out = fopen("php://output", "w");

$throttle = new BandwidthThrottle();
$throttle->setRate(100, BandwidthThrottle::KIBIBYTES); // Set limit to 100KiB/s
$throttle->throttle($out);

stream_copy_to_stream($in, $out);
```

A more sophisticated scenario would be applying multiple throttles on one
resource. E.g. the overall bandwidth for the host should be throttled to 1MiB/s
and 100KiB/s per request. This will require a shared storage for the 1MiB/s:

```php
use bandwidthThrottle\BandwidthThrottle;
use bandwidthThrottle\tokenBucket\storage\FileStorage;

$in  = fopen(__DIR__ . "/resources/video.mpg", "r");
$out = fopen("php://output", "w");

$hostThrottle = new BandwidthThrottle();
$hostThrottle->setRate(1, BandwidthThrottle::MIBIBYTES); // Set limit to 1MiB/s
$hostThrottle->setStorage(new FileStorage(__DIR__ . "/host.throttle"));
$hostThrottle->throttle($out);

$requestThrottle = new BandwidthThrottle();
$requestThrottle->setRate(100, BandwidthThrottle::KIBIBYTES); // Set limit to 100KiB/s
$requestThrottle->throttle($out);

stream_copy_to_stream($in, $out);
```

# License and authors

This project is free and under the WTFPL.
Responsible for this project is Markus Malkusch markus@malkusch.de.

## Donations

If you like this project and feel generous donate a few Bitcoins here:
[1335STSwu9hST4vcMRppEPgENMHD2r1REK](bitcoin:1335STSwu9hST4vcMRppEPgENMHD2r1REK)

[![Build Status](https://travis-ci.org/bandwidth-throttle/bandwidth-throttle.svg?branch=master)](https://travis-ci.org/bandwidth-throttle/bandwidth-throttle)
