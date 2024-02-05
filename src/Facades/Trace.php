<?php

namespace Vinelab\Tracing\Facades;

use Illuminate\Support\Facades\Facade;
use Vinelab\Tracing\Contracts\Extractor;
use Vinelab\Tracing\Contracts\Injector;
use Vinelab\Tracing\Contracts\Span;
use Vinelab\Tracing\Contracts\SpanContext;
use Vinelab\Tracing\Contracts\Tracer;

/**
 * @see \Vinelab\Tracing\Contracts\Tracer
 *
 * @method static Span startSpan(string $name, SpanContext $spanContext = null, ?int $timestamp = null, $kind = null)
 * @method static Span getRootSpan()
 * @method static Span getCurrentSpan()
 * @method static string|null getUUID()
 * @method static SpanContext|null extract($carrier, string $format)
 * @method static mixed inject($carrier, string $format)
 * @method static mixed injectContext($carrier, string $format, SpanContext $spanContext)
 * @method static array registerExtractionFormat(string $format, Extractor $extractor)
 * @method static array registerInjectionFormat(string $format, Injector $injector)
 * @method static void flush()
 */
class Trace extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    public static function getFacadeAccessor()
    {
        return Tracer::class;
    }
}
