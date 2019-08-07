<?php

namespace Vinelab\Tracing\Contracts;

interface Tracer
{
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
    public function startSpan(string $name, SpanContext $spanContext = null): Span;

    /**
     * Retrieve the root span of the service
     *
     * @return Span|null
     */
    public function getRootSpan(): ?Span;

    /**
     * Retrieve the most recently activated span.
     *
     * @return Span|null
     */
    public function getCurrentSpan(): ?Span;

    /**
     * @return string
     */
    public function getUUID(): ?string;

    /**
     * Extract span context from from a given carrier using the format descriptor
     * that tells tracer how to decode it from the carrier parameters
     *
     * @param  mixed $carrier
     * @param  string  $format
     * @return SpanContext|null
     */
    public function extract($carrier, string $format): ?SpanContext;

    /**
     * Implicitly inject current span context using the format descriptor that
     * tells how to encode trace info in the carrier parameters
     *
     * @param  mixed $carrier
     * @param  string  $format
     * @return mixed $carrier
     */
    public function inject($carrier, string $format);

    /**
     * Inject specified span context into a given carrier using the format descriptor
     * that tells how to encode trace info in the carrier parameters
     *
     * @param  $carrier
     * @param  string  $format
     * @param  SpanContext  $spanContext
     * @return mixed $carrier
     */
    public function injectContext($carrier, string $format, SpanContext $spanContext);

    /**
     * Register extractor for new format
     *
     * @param  string  $format
     * @param  Extractor  $extractor
     * @return array
     */
    public function registerExtractionFormat(string $format, Extractor $extractor): array;

    /**
     * Register injector for new format
     *
     * @param  string  $format
     * @param  Injector  $injector
     * @return array
     */
    public function registerInjectionFormat(string $format, Injector $injector): array;

    /**
     * Calling this will flush any pending spans to the transport and reset the state of the tracer.
     * Make sure this method is called after the request is finished.
     */
    public function flush(): void;
}
