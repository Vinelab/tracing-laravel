<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tracing Driver
    |--------------------------------------------------------------------------
    |
    | If you're a Jaeger user, we recommend you avail of zipkin driver with zipkin
    | compatible HTTP endpoint. Refer to Jaeger documentation for more details.
    |
    | Supported: "zipkin", "null"
    |
    */

    'driver' => env('TRACING_DRIVER', 'zipkin'),

    /*
    |--------------------------------------------------------------------------
    | Service Name
    |--------------------------------------------------------------------------
    |
    | Use this to lookup your application (microservice) on a tracing dashboard.
    |
    */

    'service_name' => env('TRACING_SERVICE_NAME', 'example'),

    /*
    |--------------------------------------------------------------------------
    | Middleware
    |--------------------------------------------------------------------------
    |
    | Configure settings for tracing HTTP requests. You can exclude certain paths
    | from tracing like '/horizon/api/*' (note that we can use wildcards), allow
    | headers to be logged or hide values for ones that have sensitive info. It
    | is also possible to specify content types for which you want to log
    | request and response bodies.
    |
    */

    'middleware' => [
        'excluded_paths' => [
            //
        ],

        'allowed_headers' => [
            '*'
        ],

        'sensitive_headers' => [
            //
        ],

        'sensitive_input' => [
            //
        ],

        'payload' => [
            'content_types' => [
                'application/json',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Errors
    |--------------------------------------------------------------------------
    |
    | Whether you want to automatically tag span with error=true
    | to denote the operation represented by the Span has failed
    | when error message was logged
    |
    */

    'errors' => true,

    /*
    |--------------------------------------------------------------------------
    | Zipkin
    |--------------------------------------------------------------------------
    |
    | Configure settings for a zipkin driver like whether you want to use
    | 128-bit Trace IDs and what is the max value size for flushed span
    | tags in bytes. Values bigger than this amount will be discarded
    | but you will still see whether certain tag was reported or not.
    |
    */

    'zipkin' => [
        'host' => env('ZIPKIN_HOST', 'localhost'),
        'port' => env('ZIPKIN_PORT', 9411),
        'options' => [
            '128bit' => false,
            'max_tag_len' => 1048576,
            'request_timeout' => 5,
        ],
    ],

];
