<?php

namespace Vinelab\Tracing\Drivers\Zipkin\Extractors;

use Vinelab\Tracing\Drivers\Zipkin\Propagation\AMQP;
use Zipkin\Propagation\Getter;

class AMQPExtractor extends ZipkinExtractor
{
    /**
     * @return AMQP
     */
    protected function getGetter(): Getter
    {
        return new AMQP();
    }
}
