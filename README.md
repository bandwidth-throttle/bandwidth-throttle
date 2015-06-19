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

## Example

This example will stream a video with a rate of 100KiB/s to the browser:

```php
use bandwidthThrottle\BandwidthThrottle;

$in  = fopen(__DIR__ . "/resources/video.mpg", "r");
$out = fopen("php://output", "w");

$throttle = new BandwidthThrottle();
$throttle->setRateInKiBperSecond(100); // Set limit to 100KiB/s
$throttle->throttle($out);

stream_copy_to_stream($in, $out);
```

# License and authors

This project is free and under the WTFPL.
Responsible for this project is Markus Malkusch markus@malkusch.de.

## Donations

If you like this project and feel generous donate a few Bitcoins here:
[1335STSwu9hST4vcMRppEPgENMHD2r1REK](bitcoin:1335STSwu9hST4vcMRppEPgENMHD2r1REK)

[![Build Status](https://travis-ci.org/bandwidth-throttle/bandwidth-throttle.svg?branch=master)](https://travis-ci.org/bandwidth-throttle/bandwidth-throttle)
