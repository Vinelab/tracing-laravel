<?php

namespace Vinelab\Tracing\Tests\Zipkin;

use Illuminate\Console\Events\CommandStarting;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Arr;
use Mockery;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Tests\Fixtures\DummyOutput;
use Vinelab\Tracing\Listeners\TraceCommand;
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

            $this->assertEquals('Console Command', Arr::get($span, 'name'));
            $this->assertEquals('cli', Arr::get($span, 'tags.type'));
            $this->assertContains('phpunit', Arr::get($span, 'tags.argv'));

            return true;
        }));
    }
}
