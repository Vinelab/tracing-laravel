<?php

namespace Vinelab\Tracing\Drivers\Zipkin\Extractors;

use Vinelab\Tracing\Drivers\Zipkin\Propagation\GooglePubSub;
use Zipkin\Propagation\Getter;

class GooglePubSubExtractor extends ZipkinExtractor
{
    /**
     * @return GooglePubSub
     */
    protected function getGetter(): Getter
    {
        return new GooglePubSub();
    }
}
