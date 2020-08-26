<?php

namespace Vinelab\Tracing\Listeners;

use Illuminate\Container\Container;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Jobs\Job;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use JsonSerializable;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionParameter;
use Vinelab\Tracing\Contracts\ShouldBeTraced;
use Vinelab\Tracing\Contracts\Span;
use Vinelab\Tracing\Contracts\SpanContext;
use Vinelab\Tracing\Contracts\Tracer;

class QueueJobSubscriber
{
    /**
     * @var ContainerInterface
     */
    protected $app;

    /**
     * QueueJobSubscriber constructor.
     * @param  Container  $app
     */
    public function __construct(Container $app)
    {
        $this->app = $app;
    }

    /**
     * @param  JobProcessing  $event
     */
    public function onJobProcessing(JobProcessing $event)
    {
        $this->startQueueJobSpan($event);
    }

    /**
     * @param  JobProcessed  $event
     */
    public function onJobProcessed(JobProcessed $event)
    {
        $this->closeQueueJobSpan($event);
    }

    /**
     * @param  JobFailed  $event
     */
    public function onJobFailed(JobFailed $event)
    {
        $this->closeQueueJobSpan($event);
    }

    /**
     * @param  JobProcessing  $event
     */
    protected function startQueueJobSpan(JobProcessing $event)
    {
        /** @var Job $job */
        $job = $event->job;
        /** @var mixed $jobInstance */
        $jobInstance = unserialize(Arr::get($job->payload(), 'data.command'));

        if (in_array(ShouldBeTraced::class, class_implements($jobInstance))) {
            $jobInput = $this->retrieveQueueJobInput($jobInstance);

            /** @var Span $span */
            $span = $this->app[Tracer::class]->startSpan(class_basename($job->resolveName()), $jobInput->first(function ($attr) {
                return $attr instanceof SpanContext;
            }));

            $span->tag('type', 'queue');
            $span->tag('connection_name', $event->connectionName);
            $span->tag('queue_name', $jobInstance->queue);
            $span->tag('job_input', json_encode($this->normalizeQueueJobInputForLogging($jobInput)));

            $this->app->instance('tracing.queue.span', $span);
        }
    }

    /**
     * @param JobProcessed|JobFailed $event
     */
    protected function closeQueueJobSpan($event)
    {
        if ($this->app->has('tracing.queue.span')) {
            if ($event instanceof JobFailed) {
                $this->app->get('tracing.queue.span')->tag('error', 'true');
                $this->app->get('tracing.queue.span')->tag('error_message', $event->exception->getMessage());
            }

            $this->app->get('tracing.queue.span')->finish();

            // If it's a sync driver, the main process will flush, we don't want to do that prematurely
            if ($event->connectionName != 'sync') {
                $this->app[Tracer::class]->flush();
            }
        }
    }

    /**
     * @param mixed $jobInstance
     * @return Collection
     */
    protected function retrieveQueueJobInput($jobInstance): Collection
    {
        $class = new ReflectionClass($jobInstance);
        $constructor = $class->getConstructor();

        return collect(optional($constructor)->getParameters())
            ->filter(function (ReflectionParameter $param) use ($class) {
                return $class->hasProperty($param->name);
            })
            ->mapWithKeys(function (ReflectionParameter $param) use ($class, $jobInstance) {
                $prop = $class->getProperty($param->name);

                $prop->setAccessible(true);

                return [$param->name => $prop->getValue($jobInstance)];
            });
    }

    /**
     * @param  Collection  $jobInput
     * @return array
     */
    protected function normalizeQueueJobInputForLogging(Collection $jobInput): array
    {
        return $jobInput
            ->reject(function ($attr) {
                return $attr instanceof SpanContext;
            })
            ->map(function ($attr) {
                if (is_scalar($attr) || is_array($attr)) {
                    return $attr;
                }

                if (is_object($attr) && $attr instanceof Arrayable) {
                    return $attr->toArray();
                }

                if (is_object($attr) && $attr instanceof Jsonable) {
                    return $attr->toJson();
                }

                if (is_object($attr) && $attr instanceof JsonSerializable) {
                    return $attr->jsonSerialize();
                }

                return $attr;
            })
            ->toArray();
    }
}
