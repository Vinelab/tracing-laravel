<?php

namespace Vinelab\Tracing\Tests\Zipkin;

use Exception;
use Illuminate\Container\Container;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Arr;
use Illuminate\Support\Fluent;
use Mockery;
use PHPUnit\Framework\TestCase;
use Vinelab\Tracing\Contracts\Tracer;
use Vinelab\Tracing\Listeners\QueueJobSubscriber;
use Vinelab\Tracing\Tests\Fixtures\ExampleJob;
use Vinelab\Tracing\Tests\Fixtures\NoopReporter;

class QueueJobSubscriberTest extends TestCase
{
    use InteractsWithZipkin;

    public function test_job_processed()
    {
        $container = new Container();

        $reporter = Mockery::spy(NoopReporter::class);
        $tracer = $this->createTracer($reporter);

        $container->instance(Tracer::class, $tracer);

        $jobInstance = new ExampleJob(
            "Example Payload",
            "",
            new Fluent(['name' => 'John Doe']),
            $tracer->startSpan("Example")->getContext()
        );
        $jobInstance->onConnection("sync");
        $jobInstance->onQueue("default");

        $job = new SyncJob($container, json_encode([
            'job' => ExampleJob::class,
            'displayName' => '',
            'data' => [
                'command' => serialize($jobInstance)
            ]
        ]), "sync", "default");

        $subscriber = new QueueJobSubscriber($container);

        $subscriber->onJobProcessing(new JobProcessing("sync", $job));
        $subscriber->onJobProcessed(new JobProcessed("sync", $job));

        $tracer->flush();

        $reporter->shouldHaveReceived('report')->with(Mockery::on(function ($spans) {
            $span = $this->shiftSpan($spans);

            $parentId = Arr::get($span, 'id');

            $span = $this->shiftSpan($spans);

            $this->assertEquals($parentId, Arr::get($span, 'parentId'));
            $this->assertEquals('ExampleJob', Arr::get($span, 'name'));
            $this->assertEquals('sync', Arr::get($span, 'tags.connection_name'));
            $this->assertEquals('default', Arr::get($span, 'tags.queue_name'));
            $this->assertEquals([
                'primitive' => 'Example Payload',
                'fluent' => [
                    'name' => 'John Doe',
                ],
            ], json_decode(Arr::get($span, 'tags.job_input'), true));

            return true;
        }));
    }

    public function test_job_failed()
    {
        $container = new Container();

        $reporter = Mockery::spy(NoopReporter::class);
        $tracer = $this->createTracer($reporter);

        $container->instance(Tracer::class, $tracer);

        $jobInstance = new ExampleJob(
            "Example Payload",
            "",
            new Fluent(['name' => 'John Doe']),
            $tracer->startSpan("Example")->getContext()
        );
        $jobInstance->onConnection("sync");
        $jobInstance->onQueue("default");

        $job = new SyncJob($container, json_encode([
            'job' => ExampleJob::class,
            'displayName' => '',
            'data' => [
                'command' => serialize($jobInstance)
            ]
        ]), "sync", "default");

        $subscriber = new QueueJobSubscriber($container);

        $subscriber->onJobProcessing(new JobProcessing("sync", $job));
        $subscriber->onJobFailed(new JobFailed("sync", $job, new Exception("whatever")));

        $tracer->flush();

        $reporter->shouldHaveReceived('report')->with(Mockery::on(function ($spans) {
            $span = $this->shiftSpan($spans);

            $parentId = Arr::get($span, 'id');

            $span = $this->shiftSpan($spans);

            $this->assertEquals($parentId, Arr::get($span, 'parentId'));
            $this->assertEquals('ExampleJob', Arr::get($span, 'name'));
            $this->assertEquals('sync', Arr::get($span, 'tags.connection_name'));
            $this->assertEquals('default', Arr::get($span, 'tags.queue_name'));
            $this->assertEquals([
                'primitive' => 'Example Payload',
                'fluent' => [
                    'name' => 'John Doe',
                ],
            ], json_decode(Arr::get($span, 'tags.job_input'), true));
            $this->assertEquals('true', Arr::get($span, 'tags.error'));

            return true;
        }));
    }
}
