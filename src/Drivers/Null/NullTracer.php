<?php

namespace Vinelab\Tracing\Drivers\Null;

use Vinelab\Tracing\Contracts\Extractor;
use Vinelab\Tracing\Contracts\Injector;
use Vinelab\Tracing\Contracts\Span;
use Vinelab\Tracing\Contracts\SpanContext;
use Vinelab\Tracing\Contracts\Tracer;

class NullTracer implements Tracer
{
    /**
     * @var NullSpan|null
     */
    protected $currentSpan;

    /**
     * @var NullSpan|null
     */
    protected $rootSpan;

    /**
     * Start a new span based on a parent trace context. The context may come either from
     * external source (extracted from HTTP request, AMQP message, etc., see extract method)
     * or received from another span in this service.
     *
     * If parent context does not contain a trace, a new trace will be implicitly created.
     *
     * @param  string  $name
     * @param  SpanContext|null  $spanContext
     * @return Span
     */
    public function startSpan(string $name, ?SpanContext $spanContext = null): Span
    {
        if ($this->rootSpan) {
            $span = new NullSpan(false);
        } else {
            $span = new NullSpan(true);
            $this->rootSpan = $span;
        }

        $this->currentSpan = $span;

        return $span;
    }

    /**
     * Retrieve the root span of the service
     *
     * @return Span|null
     */
    public function getRootSpan(): ?Span
    {
        return $this->rootSpan;
    }

    /**
     * Retrieve the most recently activated span.
     *
     * @return Span|null
     */
    public function getCurrentSpan(): ?Span
    {
        return $this->currentSpan;
    }

    /**
     * @return string
     */
    public function getUUID(): ?string
    {
        return null;
    }

    /**
     * Extract span context from from a given carrier using the format descriptor
     * that tells tracer how to decode it from the carrier parameters
     *
     * @param  mixed $carrier
     * @param  string  $format
     * @return SpanContext|null
     */
    public function extract($carrier, string $format): ?SpanContext
    {
        return null;
    }

    /**
     * Implicitly inject current span context using the format descriptor that
     * tells how to encode trace info in the carrier parameters
     *
     * @param  mixed $carrier
     * @param  string  $format
     * @return mixed $carrier
     */
    public function inject($carrier, string $format)
    {
        return $carrier;
    }

    /**
     * Inject specified span context into a given carrier using the format descriptor
     * that tells how to encode trace info in the carrier parameters
     *
     * @param  mixed $carrier
     * @param  string  $format
     * @param  SpanContext  $spanContext
     * @return mixed $carrier
     */
    public function injectContext($carrier, string $format, SpanContext $spanContext)
    {
        return $carrier;
    }

    /**
     * Register extractor for new format
     *
     * @param  string  $format
     * @param  Extractor  $extractor
     * @return array
     */
    public function registerExtractionFormat(string $format, Extractor $extractor): array
    {
        return [];
    }

    /**
     * Register injector for new format
     *
     * @param  string  $format
     * @param  Injector  $injector
     * @return array
     */
    public function registerInjectionFormat(string $format, Injector $injector): array
    {
        return [];
    }

    /**
     * Calling this will flush any pending spans to the transport and reset the state of the tracer.
     * Make sure this method is called after the request is finished.
     */
    public function flush(): void
    {
        $this->rootSpan = null;
        $this->currentSpan = null;
    }
}
