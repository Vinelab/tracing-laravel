<?php

namespace Vinelab\Tracing\Tests\Zipkin;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Mockery;
use PHPUnit\Framework\TestCase;
use Vinelab\Tracing\Middleware\TraceRequests;
use Vinelab\Tracing\Tests\Fixtures\NoopReporter;

class MiddlewareTest extends TestCase
{
    use InteractsWithZipkin;

    /** @test */
    public function trace_http_requests()
    {
        $reporter = Mockery::spy(NoopReporter::class);
        $tracer = $this->createTracer($reporter);

        $request = Request::create('/example?token=secret', 'POST', [], [], [], [], json_encode([
            'data' => 'Catherine Dupuy'
        ]));
        $request->headers->set('Content-Type', 'application/json');

        $response = new JsonResponse([
            'message' => 'Unprocessable Entity'
        ], 422);

        $middleware = new TraceRequests($tracer, $this->mockConfig());
        $middleware->handle($request, function ($req) {});
        $middleware->terminate($request, $response);
        $tracer->flush();

        $reporter->shouldHaveReceived('report')->with(Mockery::on(function ($spans) {
            $span = $this->shiftSpan($spans);

            $this->assertEquals('Http Request', Arr::get($span, 'name'));
            $this->assertEquals('http', Arr::get($span, 'tags.type'));
            $this->assertEquals('POST', Arr::get($span, 'tags.request_method'));
            $this->assertEquals('example', Arr::get($span, 'tags.request_path'));
            $this->assertEquals('/example?token=secret', Arr::get($span, 'tags.request_uri'));
            $this->assertContains('application/json', Arr::get($span, 'tags.request_headers'));
            $this->assertContains('Catherine Dupuy', Arr::get($span, 'tags.request_input'));
            $this->assertEquals('127.0.0.1', Arr::get($span, 'tags.request_ip'));

            $this->assertContains('422', Arr::get($span, 'tags.response_status'));
            $this->assertContains('application/json', Arr::get($span, 'tags.response_headers'));
            $this->assertContains('Unprocessable Entity', Arr::get($span, 'tags.response_content'));

            return true;
        }));
    }

    /**
     * @return Repository|Mockery\LegacyMockInterface|Mockery\MockInterface
     */
    protected function mockConfig()
    {
        $config = Mockery::mock(Repository::class);
        $config->shouldReceive('get')
            ->with('tracing.logging.content_types')
            ->andReturn(['application/json']);

        return $config;
    }
}
