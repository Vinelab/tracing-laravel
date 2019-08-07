<?php

namespace Vinelab\Tracing\Drivers\Zipkin\Injectors;

use Vinelab\Tracing\Contracts\Injector;
use Vinelab\Tracing\Contracts\SpanContext;
use Zipkin\Propagation\Propagation;
use Zipkin\Propagation\Setter;

abstract class ZipkinInjector implements Injector
{
    /**
     * @var Propagation
     */
    protected $propagation;

    /**
     * Serialize span into given carrier
     *
     * @param  SpanContext  $spanContext
     * @param  mixed  $carrier
     */
    public function inject(SpanContext $spanContext, &$carrier): void
    {
        $inject = $this->propagation->getInjector($this->getSetter());
        $inject($spanContext->getRawContext(), $carrier);
    }

    /**
     * @param  Propagation  $propagation
     * @return $this
     */
    public function setPropagation(Propagation $propagation): self
    {
        $this->propagation = $propagation;
        return $this;
    }

    abstract protected function getSetter(): Setter;
}
