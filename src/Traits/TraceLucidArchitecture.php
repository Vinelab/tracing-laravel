<?php

namespace Vinelab\Tracing\Traits;

use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Lucid\Foundation\Events\FeatureStarted;
use Lucid\Foundation\Events\JobStarted;
use Lucid\Foundation\Events\OperationStarted;
use Vinelab\Tracing\Facades\Trace;

/**
 * Trait TraceLucidArchitecture
 *
 * Provides tracing configuration for projects based on Lucid Architecture
 *
 * https://github.com/lucid-architecture/laravel
 * https://packagist.org/packages/lucid-arch/laravel-foundation
 *
 * @package Vinelab\Tracing\Traits
 */
trait TraceLucidArchitecture
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

    protected function highlightErrors()
    {
        Event::listen(MessageLogged::class, function (MessageLogged $event) {
            if ($event->level == 'error') {
                optional(Trace::getRootSpan())->tag('error', 'true');
            }
        });
    }
}
