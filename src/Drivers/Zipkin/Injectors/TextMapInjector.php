<?php

namespace Vinelab\Tracing\Drivers\Zipkin\Injectors;

use Zipkin\Propagation\Map;
use Zipkin\Propagation\Setter;

class TextMapInjector extends ZipkinInjector
{
    /**
     * @return Map
     */
    protected function getSetter(): Setter
    {
        return new Map();
    }
}
