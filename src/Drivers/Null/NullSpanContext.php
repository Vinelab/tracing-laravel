<?php

namespace Vinelab\Tracing\Drivers\Null;

use Vinelab\Tracing\Contracts\SpanContext;

class NullSpanContext implements SpanContext
{
    /**
     * Returns underlying (original) span context.
     *
     * @return mixed
     */
    public function getRawContext()
    {
        return null;
    }
}
