<?php

namespace Vinelab\Tracing\Drivers\Zipkin\Extractors;

use Zipkin\Propagation\Getter;
use Zipkin\Propagation\Map;

class TextMapExtractor extends ZipkinExtractor
{
    /**
     * @return Map
     */
    protected function getGetter(): Getter
    {
        return new Map();
    }
}
