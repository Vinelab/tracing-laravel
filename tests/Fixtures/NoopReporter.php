<?php

namespace Vinelab\Tracing\Tests\Fixtures;

use Zipkin\Recording\Span as MutableSpan;
use Zipkin\Reporter;

class NoopReporter implements Reporter
{
    /**
     * @param  MutableSpan[]  $spans
     * @return void
     */
    public function report(array $spans): void
    {
        //
    }
}
