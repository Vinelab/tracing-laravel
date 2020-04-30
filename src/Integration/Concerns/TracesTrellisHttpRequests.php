<?php

namespace Vinelab\Tracing\Integration\Concerns;

use Trellis\Clients\Facades\Intercept;
use Vinelab\Tracing\Facades\Trace;
use Vinelab\Tracing\Propagation\Formats;

/**
 * Enables tracing for Trellis inter-service communication over HTTP
 */
trait TracesTrellisHttpRequests
{
    protected function traceTrellisHttpRequests()
    {
        Intercept::request(function ($request) {
            return Trace::inject($request, Formats::VINELAB_HTTP);
        });
    }
}
