<?php

namespace Vinelab\Tracing\Drivers\Zipkin\Extractors;

use Vinelab\Tracing\Drivers\Zipkin\Propagation\IlluminateHttp;
use Zipkin\Propagation\Getter;

class IlluminateHttpExtractor extends ZipkinExtractor
{
    /**
     * @return IlluminateHttp
     */
    protected function getGetter(): Getter
    {
        return new IlluminateHttp();
    }
}
