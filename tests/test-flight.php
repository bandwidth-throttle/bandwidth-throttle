<?php

use bandwidthThrottle\BandwidthThrottle;

// Provide resources for BandwidthThrottle
$video = fopen(__DIR__ . "/../classes/video.mpg", "w");
fputs($video, "Some content");
fclose($video);

// Provide resources for Readme
$video = fopen(__DIR__ . "/../video.mpg", "w");
fputs($video, "Some content");
fclose($video);

$eventDispatcher->register(
    \Cundd\TestFlight\TestRunner\TestRunnerInterface::EVENT_TEST_WILL_RUN,
    function (\Cundd\TestFlight\Event\Event $event) {
        $throttle = new BandwidthThrottle();
        $event->getContext()->setVariable('throttle', $throttle);
    }
);
