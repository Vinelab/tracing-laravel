<?php

namespace Vinelab\Tracing\Drivers\Zipkin;

use Vinelab\Tracing\Contracts\Span;
use Vinelab\Tracing\Contracts\SpanContext;
use Zipkin\Span as RawSpan;
use function Zipkin\Timestamp\now;

class ZipkinSpan implements Span
{
    /**
     * @var RawSpan
     */
    protected $span;

    /**
     * @var bool
     */
    protected $isRoot;

    /**
     * ZipkinSpan constructor.
     * @param  RawSpan  $span
     * @param  bool  $isRoot
     */
    public function __construct(RawSpan $span, bool $isRoot)
    {
        $this->span = $span;
        $this->isRoot = $isRoot;
    }

    /**
     * Sets the string name for the logical operation this span represents.
     *
     * @param  string  $name
     */
    public function setName(string $name): void
    {
        $this->span->setName($name);
    }

    /**
     * Tags give your span context for search, viewing and analysis. For example,
     * a key "your_app.version" would let you lookup spans by version.
     *
     * @param  string  $key
     * @param  string|null  $value
     */
    public function tag(string $key, ?string $value = null): void
    {
        if (mb_strlen($value) > ZipkinTracer::getMaxTagLen()) {
            $value = sprintf("Value exceeds the maximum allowed length of %d bytes", ZipkinTracer::getMaxTagLen());
        }

        $this->span->tag($key, $value ?? '');
    }

    /**
     * Notify that operation has finished.
     * Span duration is derived by subtracting the start
     * timestamp from this, and set when appropriate.
     */
    public function finish(): void
    {
        $this->span->finish();
    }

    /**
     * Associates an event that explains latency with a timestamp.
     *
     * @param  string  $message
     */
    public function annotate(string $message): void
    {
        $this->span->annotate($message, now());
    }

    /**
     * Log structured data. Not supported in Zipkin instrumentation.
     *
     * @param  array  $fields  key:value pairs
     */
    public function log(array $fields): void
    {
        //
    }

    /**
     * @return bool
     */
    public function isRoot(): bool
    {
        return $this->isRoot;
    }

    /**
     * @return SpanContext
     */
    public function getContext(): SpanContext
    {
        return new ZipkinSpanContext($this->span->getContext());
    }
}
