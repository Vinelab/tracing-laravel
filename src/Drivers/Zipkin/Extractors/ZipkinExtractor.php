<?php

namespace Vinelab\Tracing\Drivers\Zipkin\Extractors;

use Vinelab\Tracing\Contracts\Extractor;
use Vinelab\Tracing\Contracts\SpanContext;
use Vinelab\Tracing\Drivers\Zipkin\ZipkinSpanContext;
use Zipkin\Propagation\Getter;
use Zipkin\Propagation\Propagation;
use Zipkin\Propagation\TraceContext;

abstract class ZipkinExtractor implements Extractor
{
    /**
     * @var Propagation
     */
    protected $propagation;

    /**
     * Extract span context from given carrier
     *
     * @param  mixed  $carrier
     * @return SpanContext|null
     */
    public function extract($carrier): ?SpanContext
    {
        $extract = $this->propagation->getExtractor($this->getGetter());
        $context = $extract($carrier);

        if ($context instanceof TraceContext) {
            return new ZipkinSpanContext($context);
        }

        return null;
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

    abstract protected function getGetter(): Getter;
}
