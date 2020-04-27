<?php

namespace Vinelab\Tracing\Traits;

use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Lucid\Foundation\Events\FeatureStarted;
use Lucid\Foundation\Events\JobStarted;
use Lucid\Foundation\Events\OperationStarted;
use Trellis\Clients\Facades\Intercept;
use Vinelab\Tracing\Facades\Trace;
use Vinelab\Tracing\Propagation\Formats;

/**
 * Provides tracing configuration for Lucid based projects
 */
trait LucidTrait
{

    protected function renameRootSpanBasedOnFeature()
    {
        Event::listen(FeatureStarted::class, function (FeatureStarted $event) {
            optional(Trace::getRootSpan())->setName($event->name);
        });
    }

    protected function annotateRunningOperations()
    {
        Event::listen(OperationStarted::class, function (OperationStarted $event) {
            optional(Trace::getRootSpan())->annotate($event->name);
        });
    }

    protected function annotateRunningJobs()
    {
        Event::listen(JobStarted::class, function (JobStarted $event) {
            optional(Trace::getRootSpan())->annotate($event->name);
        });
    }

    protected function injectTraceIntoDispatchedRequests()
    {
        Intercept::request(function ($request) {
            return Trace::inject($request, Formats::VINELAB_HTTP);
        });
    }

    protected function highlightErrors()
    {
        Event::listen(MessageLogged::class, function (MessageLogged $event) {
            if ($event->level == 'error') {
                optional(Trace::getRootSpan())->tag('error', 'true');
            }
        });
    }
}
