<?php

namespace Vinelab\Tracing\Tests\Zipkin;

use Illuminate\Console\Events\CommandStarting;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Arr;
use Mockery;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Vinelab\Tracing\Listeners\TraceCommand;
use Vinelab\Tracing\Tests\Fixtures\DummyOutput;
use Vinelab\Tracing\Tests\Fixtures\ExampleCommand;
use Vinelab\Tracing\Tests\Fixtures\NoopReporter;

class TraceCommandTest extends TestCase
{
    use InteractsWithZipkin;

    /** @test */
    public function trace_console_command()
    {
        $reporter = Mockery::spy(NoopReporter::class);
        $tracer = $this->createTracer($reporter);

        $artisan = Mockery::mock(Kernel::class);
        $artisan->shouldReceive('all')->andReturn([
            'example' => new ExampleCommand(),
        ]);

        $listener = new TraceCommand($tracer, $artisan);
        $listener->handle(new CommandStarting('example', new ArrayInput(['test']), new DummyOutput()));

        $tracer->flush();

        $reporter->shouldHaveReceived('report')->with(Mockery::on(function ($spans) {
            $span = $this->shiftSpan($spans);

            $this->assertEquals('artisan example', $span->getName());
            $this->assertEquals('cli', Arr::get($span->getTags(), 'type'));
            $this->assertStringContainsString('phpunit', Arr::get($span->getTags(), 'argv'));

            return true;
        }));
    }
}
