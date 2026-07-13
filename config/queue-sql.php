<?php

return [
    // Default number of rows per job. Overridden per call by queue(chunk: N).
    'chunk' => 1000,

    // Default per-job retry count and backoff (seconds, or an array of seconds).
    'tries' => 1,
    'backoff' => 0,

    // Default queue routing. null = Laravel's default connection / queue name.
    'connection' => null,
    'queue' => null,

    // Default staggering. null = no throttle / no delay.
    'throttle' => null,
    'delay' => null,
];
