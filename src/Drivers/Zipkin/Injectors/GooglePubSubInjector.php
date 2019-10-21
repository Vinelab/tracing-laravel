<?php

namespace Vinelab\Tracing\Drivers\Zipkin\Injectors;

use Vinelab\Tracing\Drivers\Zipkin\Propagation\GooglePubSub;
use Zipkin\Propagation\Setter;

class GooglePubSubInjector extends ZipkinInjector
{
    /**
     * @return GooglePubSub
     */
    protected function getSetter(): Setter
    {
        return new GooglePubSub();
    }
}
