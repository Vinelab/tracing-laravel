<?php

namespace Vinelab\Tracing\Tests\Zipkin;

use Vinelab\Tracing\Drivers\Zipkin\ZipkinTracer;
use Zipkin\Recording\Span;
use Zipkin\Reporter;

trait InteractsWithZipkin
{
    /**
     * @param  Reporter  $reporter
     * @param  string|null  $serviceName
     * @param  string  $host
     * @param  int  $port
     * @param  bool  $usesTraceId128bits
     * @return ZipkinTracer
     */
    protected function createTracer(
        Reporter $reporter,
        string $serviceName = 'example',
        string $host = 'localhost',
        int $port = 9411,
        bool $usesTraceId128bits = false
    ): ZipkinTracer
    {
        $tracer = new ZipkinTracer($serviceName, $host, $port, $usesTraceId128bits, $reporter);
        $tracer->init();

        return $tracer;
    }

    /**
     * @param  array  $spans
     * @return array
     */
    protected function shiftSpan(array &$spans): array
    {
        /** @var Span $span */
        $span = array_shift($spans);
        return $span->toArray();
    }
}
