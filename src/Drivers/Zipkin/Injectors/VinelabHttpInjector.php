<?php

namespace Vinelab\Tracing\Drivers\Zipkin\Injectors;

use Vinelab\Tracing\Drivers\Zipkin\Propagation\VinelabHttp;
use Zipkin\Propagation\Setter;

class VinelabHttpInjector extends ZipkinInjector
{
    /**
     * @return VinelabHttp
     */
    protected function getSetter(): Setter
    {
        return new VinelabHttp();
    }
}
