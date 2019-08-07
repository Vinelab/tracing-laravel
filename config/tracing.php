<?php

return [

    'driver' => env('TRACING_DRIVER', 'zipkin'),

    'service_name' => env('TRACING_SERVICE_NAME', 'example'),

    'logging' => [
        'content_types' => [
            'application/json',
        ],
    ],

    'zipkin' => [
        'host' => env('ZIPKIN_HOST', 'localhost'),
        'port' => env('ZIPKIN_PORT', 9411),
        'options' => [
            '128bit' => false,
            'max_tag_len' => 1048576,
        ],
    ],

];
