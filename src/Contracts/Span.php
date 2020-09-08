<?php

namespace Vinelab\Tracing\Contracts;

interface Span
{
    /**
     * Sets the string name for the logical operation this span represents.
     *
     * @param  string  $name
     */
    public function setName(string $name): void;

    /**
     * Tags give your span context for search, viewing and analysis. For example,
     * a key "your_app.version" would let you lookup spans by version.
     *
     * @param  string  $key
     * @param  string|null  $value
     */
    public function tag(string $key, ?string $value = null): void;

    /**
     * Notify that operation has finished.
     * Span duration is derived by subtracting the start
     * timestamp from this, and set when appropriate.
     * @param int|null $timestamp
     */
    public function finish($timestamp = null): void;

    /**
     * Associates an event that explains latency with a timestamp.
     *
     * @param  string  $message
     */
    public function annotate(string $message): void;

    /**
     * Log structured data. Only supported in Jaeger instrumentation
     * despite it technically being a part of OpenTracing spec
     *
     * @param  array  $fields key:value pairs
     */
    public function log(array $fields): void;

    /**
     * @return bool
     */
    public function isRoot(): bool;

    /**
     * @return SpanContext
     */
    public function getContext(): SpanContext;
}
