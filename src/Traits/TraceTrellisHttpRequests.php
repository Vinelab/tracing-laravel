<?php

namespace Vinelab\Tracing\Traits;

use Trellis\Clients\Facades\Intercept;//https://github.com/Trelllis/service-clients
use Vinelab\Tracing\Facades\Trace;
use Vinelab\Tracing\Propagation\Formats;

/**
 * Trait TraceTrellisHttpRequests
 *
 * Provides tracing configuration for Trellis http requests - https://github.com/Trelllis/service-clients
 *
 * @package Vinelab\Tracing\Traits
 */
trait TraceTrellisHttpRequests
{
    protected function injectTraceIntoDispatchedRequests()
    {
        Intercept::request(function ($request) {
            return Trace::inject($request, Formats::VINELAB_HTTP);
        });
    }
}
