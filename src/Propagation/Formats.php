<?php

namespace Vinelab\Tracing\Propagation;

class Formats
{
    /**
     * For associative arrays
     */
    public const TEXT_MAP = 'text_map';

    /**
     * For associative arrays
     */
    public const PSR_REQUEST = 'psr_request';

    /**
     * For Illuminate\Http\Request headers
     */
    public const ILLUMINATE_HTTP = 'illuminate_http';

    /**
     * For AMQP messages
     */
    public const AMQP = 'amqp';

    /**
     * For AMQP messages
     */
    public const GOOGLE_PUBSUB = 'google_pubsub';

    /**
     * For Vinelab/http request headers
     */
    public const VINELAB_HTTP = 'vinelab_http';
}
