<?php

namespace Vinelab\Tracing\Drivers\Zipkin\Injectors;

use Vinelab\Tracing\Drivers\Zipkin\Propagation\PsrRequest;
use Zipkin\Propagation\Setter;

class PsrRequestInjector extends ZipkinInjector
{
    /**
     * @return PsrRequest
     */
    protected function getSetter(): Setter
    {
        return new PsrRequest();
    }
}
