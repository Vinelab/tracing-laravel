<?php

namespace Vinelab\Tracing\Tests\Zipkin;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Arr;
use Mockery;
use PHPUnit\Framework\TestCase;
use Vinelab\Tracing\Middleware\TraceRequests;
use Vinelab\Tracing\Tests\Fixtures\NoopReporter;

class TraceRequestsTest extends TestCase
{
    use InteractsWithZipkin;

    /** @test */
    public function trace_http_requests()
    {
        $reporter = Mockery::spy(NoopReporter::class);
        $tracer = $this->createTracer($reporter);

        $request = Request::create('/shipments/3242?token=secret', 'POST', [], [], [], [], json_encode([
            'data' => 'Catherine Dupuy'
        ]));
        $request->headers->set('Content-Type', 'application/json');
        $request->setRouteResolver(function () {
            return new Route('POST', '/shipments/{id}', function () {
                return new JsonResponse();
            });
        });

        $response = new JsonResponse([
            'message' => 'Unprocessable Entity'
        ], 422);

        $middleware = new TraceRequests($tracer, $this->mockConfig([], ['Content-Type', 'user-agent'], ['user-agent']));
        $middleware->handle($request, function ($req) {});
        $middleware->terminate($request, $response);
        $tracer->flush();

        $reporter->shouldHaveReceived('report')->with(Mockery::on(function ($spans) {
            $span = $this->shiftSpan($spans);

            $this->assertRegExp('/^POST \/?shipments\/{id}$/', Arr::get($span, 'name'));
            $this->assertEquals('http', Arr::get($span, 'tags.type'));
            $this->assertEquals('POST', Arr::get($span, 'tags.request_method'));
            $this->assertEquals('shipments/3242', Arr::get($span, 'tags.request_path'));
            $this->assertEquals('/shipments/3242?token=secret', Arr::get($span, 'tags.request_uri'));
            $this->assertContains('application/json', Arr::get($span, 'tags.request_headers'));
            $this->assertContains('This value is hidden because it contains sensitive info', Arr::get($span, 'tags.request_headers'));
            $this->assertContains('Catherine Dupuy', Arr::get($span, 'tags.request_input'));
            $this->assertEquals('127.0.0.1', Arr::get($span, 'tags.request_ip'));

            $this->assertContains('422', Arr::get($span, 'tags.response_status'));
            $this->assertContains('application/json', Arr::get($span, 'tags.response_headers'));
            $this->assertContains('Unprocessable Entity', Arr::get($span, 'tags.response_content'));

            return true;
        }));
    }

    /** @test */
    public function disable_tracing_for_specified_paths()
    {
        $reporter = Mockery::spy(NoopReporter::class);
        $tracer = $this->createTracer($reporter);

        $request = Request::create('/users/1', 'GET', [], [], [], []);

        $middleware = new TraceRequests($tracer, $this->mockConfig(['users/*']));
        $middleware->handle($request, function ($req) {});
        $middleware->terminate($request, new JsonResponse);

        $tracer->flush();

        $reporter->shouldHaveReceived('report')->with(Mockery::on(function ($spans) {
            $this->assertEmpty($spans);

            return true;
        }));
    }

    /**
     * @param  array  $excludedPaths
     * @param  array  $allowedHeaders
     * @param  array  $sensitiveHeaders
     * @return Repository|Mockery\LegacyMockInterface|Mockery\MockInterface
     */
    protected function mockConfig(array $excludedPaths = [], $allowedHeaders = [], array $sensitiveHeaders = [])
    {
        $config = Mockery::mock(Repository::class);
        $config->shouldReceive('get')
            ->with('tracing.middleware.payload.content_types')
            ->andReturn(['application/json']);

        $config->shouldReceive('get')
            ->with('tracing.middleware.excluded_paths')
            ->andReturn($excludedPaths);

        $config->shouldReceive('get')
            ->with('tracing.middleware.allowed_headers')
            ->andReturn($allowedHeaders);

        $config->shouldReceive('get')
            ->with('tracing.middleware.sensitive_headers')
            ->andReturn($sensitiveHeaders);

        return $config;
    }
}
