<?php

namespace Vinelab\Tracing\Tests\Fixtures;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Fluent;
use Vinelab\Tracing\Contracts\ShouldBeTraced;
use Vinelab\Tracing\Contracts\SpanContext;

class ExampleJob implements ShouldQueue, ShouldBeTraced
{
    use InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var string
     */
    protected $primitive;

    /**
     * @var Fluent
     */
    protected $fluent;

    /**
     * @var SpanContext
     */
    protected $spanContext;

    /**
     * ExampleJob constructor.
     * @param  string  $primitive
     * @param  string  $unusedPrimitive
     * @param  Fluent  $fluent
     * @param  SpanContext  $spanContext
     */
    public function __construct(string $primitive, string $unusedPrimitive, Fluent $fluent, SpanContext $spanContext)
    {
        $this->primitive = $primitive;
        $this->fluent = $fluent;
        $this->spanContext = $spanContext;
    }
}
