<?php

namespace Vinelab\Tracing\Drivers\Zipkin;

use Vinelab\Tracing\Contracts\SpanContext;
use Zipkin\Propagation\TraceContext;

class ZipkinSpanContext implements SpanContext
{
    /**
     * @var TraceContext
     */
    protected $context;

    /**
     * ZipkinSpanContext constructor.
     * @param  TraceContext  $spanContext
     */
    public function __construct(TraceContext $spanContext)
    {
        $this->context = $spanContext;
    }

    /**
     * Returns underlying (original) span context.
     *
     * @return mixed
     */
    public function getRawContext()
    {
        return $this->context;
    }
}
