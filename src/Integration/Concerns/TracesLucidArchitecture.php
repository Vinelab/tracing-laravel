<?php

namespace Vinelab\Tracing\Integration\Concerns;

use Illuminate\Support\Facades\Event;
use Lucid\Foundation\Events\FeatureStarted;
use Lucid\Foundation\Events\JobStarted;
use Lucid\Foundation\Events\OperationStarted;
use Vinelab\Tracing\Facades\Trace;

/**
 * Enables tracing for projects based on a Lucid Architecture
 *
 * https://github.com/lucid-architecture/laravel-microservice
 */
trait TracesLucidArchitecture
{
    protected function traceLucidArchitecture()
    {
        $this->renameRootSpanBasedOnFeature();
        $this->annotateRunningOperations();
        $this->annotateRunningJobs();
    }

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
}
