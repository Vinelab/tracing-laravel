<?php

namespace Vinelab\Tracing\Drivers\Zipkin\Injectors;

use Vinelab\Tracing\Drivers\Zipkin\Propagation\IlluminateHttp;
use Zipkin\Propagation\Setter;

class IlluminateHttpInjector extends ZipkinInjector
{
    /**
     * @return IlluminateHttp
     */
    protected function getSetter(): Setter
    {
        return new IlluminateHttp();
    }
}
