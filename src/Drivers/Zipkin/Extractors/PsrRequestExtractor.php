<?php

namespace Vinelab\Tracing\Drivers\Zipkin\Extractors;

use Vinelab\Tracing\Drivers\Zipkin\Propagation\PsrRequest;
use Zipkin\Propagation\Getter;

class PsrRequestExtractor extends ZipkinExtractor
{
    /**
     * @return PsrRequest
     */
    protected function getGetter(): Getter
    {
        return new PsrRequest();
    }
}
