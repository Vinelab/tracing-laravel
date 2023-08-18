<?php

namespace Vinelab\Tracing\Tests\Zipkin;

use Carbon\Carbon;
use Closure;
use Google\Cloud\PubSub\Message;
use GuzzleHttp\Psr7\Request as PsrRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Mockery;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use PHPUnit\Framework\TestCase;
use Vinelab\Tracing\Contracts\SpanContext;
use Vinelab\Tracing\Drivers\Zipkin\ZipkinTracer;
use Vinelab\Tracing\Propagation\Formats;
use Vinelab\Tracing\Tests\Fixtures\NoopReporter;
use Zipkin\Propagation\TraceContext;
use Zipkin\Recording\Span;
use Zipkin\Samplers\BinarySampler;
use Zipkin\Samplers\PercentageSampler;

class TracerTest extends TestCase
{
    use InteractsWithZipkin;

    /** @test */
    public function create_tracer_with_BinarySampler()
    {
        $reporter = Mockery::spy(NoopReporter::class);

        $sampler = BinarySampler::createAsAlwaysSample();

        $tracer = $this->createTracer($reporter, 'orders', '192.88.105.145', 9444, 5, false, $sampler);

        $this->assertSpan($reporter, $tracer);
    }

    /** @test  */
    public function create_tracer_with_PercentageSampler_1_rate()
    {
        $reporter = Mockery::spy(NoopReporter::class);

        $sampler = PercentageSampler::create(1);

        $tracer = $this->createTracer($reporter, 'orders', '192.88.105.145', 9444, 5, false, $sampler);

        $this->assertSpan($reporter, $tracer);
    }

    /** @test  */
    public function create_tracer_with_PercentageSampler_0_rate()
    {
        $reporter = Mockery::spy(NoopReporter::class);

        $sampler = PercentageSampler::create(0);

        $tracer = $this->createTracer($reporter, 'orders', '192.88.105.145', 9444, 5, false, $sampler);

        $this->assertSpan($reporter, $tracer,function ($spans){
            $this->assertEmpty($spans);
        });
    }

    /** @test */
    public function configure_reporter()
    {
        $reporter = Mockery::spy(NoopReporter::class);

        $tracer = $this->createTracer($reporter, 'orders', '192.88.105.145', 9444);
        $tracer->startSpan('Example');
        $tracer->flush();

        $reporter->shouldHaveReceived('report')->with(Mockery::on(function ($spans) {
            $span = $this->shiftSpan($spans);

            $this->assertEquals('orders', $span->getLocalEndpoint()->getServiceName());
            $this->assertEquals('192.88.105.145', $span->getLocalEndpoint()->getIpv4());
            $this->assertEquals('9444', $span->getLocalEndpoint()->getPort());
            $this->assertEquals(16, strlen($span->getTraceId()));

            return true;
        }));
    }

    /** @test */
    public function configure_reporter_using_hostname()
    {
        $reporter = Mockery::spy(NoopReporter::class);

        $tracer = $this->createTracer($reporter, 'example', 'localhost');
        $tracer->startSpan('Example');
        $tracer->flush();

        $reporter->shouldHaveReceived('report')->with(Mockery::on(function ($spans) {
            $span = $this->shiftSpan($spans);
            $this->assertEquals('127.0.0.1', $span->getLocalEndpoint()->getIpv4());
            return true;
        }));
    }

    /** @test */
    public function configure_reporter_using_ipv6_address()
    {
        $reporter = Mockery::spy(NoopReporter::class);

        $tracer = $this->createTracer($reporter, 'example', 'dec6:dcca:47dc:7c89:5a7c:8d6f:c9b3:96d0');
        $tracer->startSpan('Example');
        $tracer->flush();

        $reporter->shouldHaveReceived('report')->with(Mockery::on(function ($spans) {
            $span = $this->shiftSpan($spans);
            $this->assertEquals('dec6:dcca:47dc:7c89:5a7c:8d6f:c9b3:96d0', $span->getLocalEndpoint()->getIpv6());
            return true;
        }));
    }

    /** @test */
    public function configure_reporter_using_128bit_trace_ids()
    {
        $reporter = Mockery::spy(NoopReporter::class);

        $tracer = $this->createTracer($reporter, 'example', 'localhost', 9411, 5, true);
        $tracer->startSpan('Example');
        $tracer->flush();

        $reporter->shouldHaveReceived('report')->with(Mockery::on(function ($spans) {
            $span = $this->shiftSpan($spans);
            $this->assertEquals(32, strlen($span->getTraceId()));
            return true;
        }));
    }

    /** @test */
    public function customize_spans()
    {
        $reporter = Mockery::spy(NoopReporter::class);
        $tracer = $this->createTracer($reporter);

        $startTimestamp = intval(microtime(true) * 1000000);
        $span = $tracer->startSpan('Http Request', null, $startTimestamp);
        $span->setName('Create Order');
        $span->tag('request_path', 'api/orders');
        $span->annotate('Create Payment');
        $span->annotate('Update Order Status');
        $finishTimestamp = intval(microtime(true) * 1000000);
        $span->finish($finishTimestamp);
        $tracer->flush();

        $reporter->shouldHaveReceived('report')->with(
            Mockery::on(function ($spans) use ($startTimestamp, $finishTimestamp) {
                $span = $this->shiftSpan($spans);
                $this->assertEquals('Create Order', $span->getName());
                $this->assertEquals($finishTimestamp - $startTimestamp, $span->getDuration());
                $this->assertEquals('api/orders', Arr::get($span->getTags(), 'request_path'));
                $this->assertEquals('Create Payment', Arr::get($span->getAnnotations(), '0.value'));
                $this->assertEquals('Update Order Status', Arr::get($span->getAnnotations(), '1.value'));
                return true;
            })
        );
    }

    /** @test */
    public function control_span_duration()
    {
        $reporter = Mockery::spy(NoopReporter::class);
        $tracer = $this->createTracer($reporter);

        $tracer->startSpan('Example')->finish();
        $tracer->flush();

        $reporter->shouldHaveReceived('report')->with(Mockery::on(function ($spans) {
            $duration = $this->shiftSpan($spans)->getDuration();

            $this->assertTrue($duration > 0);
            return true;
        }));
    }

    /** @test */
    public function control_span_relationships()
    {
        $reporter = Mockery::spy(NoopReporter::class);
        $tracer = $this->createTracer($reporter);

        $rootSpan = $tracer->startSpan('Example 1');
        $childSpan = $tracer->startSpan('Example 2', $rootSpan->getContext());
        $tracer->flush();

        $this->assertTrue($rootSpan->isRoot());
        $this->assertFalse($childSpan->isRoot());

        $reporter->shouldHaveReceived('report')->with(Mockery::on(function ($spans) {
            $span = $this->shiftSpan($spans);

            $this->assertMatchesRegularExpression($this->getRegexpForUUID(), Arr::get($span->getTags(), 'uuid'));
            $this->assertNotNull($span->getSpanId());
            $this->assertNull($span->getParentId());

            $spanId = Arr::get($span, 'id');
            $span = $this->shiftSpan($spans);

            $this->assertEquals($spanId, Arr::get($span, 'parentId'));

            return true;
        }));
    }

    /** @test */
    public function set_max_tag_len()
    {
        $reporter = Mockery::spy(NoopReporter::class);
        $tracer = $this->createTracer($reporter);

        ZipkinTracer::setMaxTagLen(10);
        $span = $tracer->startSpan('Http Request');
        $span->tag('response_content', 'Attaquer chance mur huit.');
        $tracer->flush();

        $reporter->shouldHaveReceived('report')->with(Mockery::on(function ($spans) {
            $span = $this->shiftSpan($spans);

            $this->assertEquals(
                'Value exceeds the maximum allowed length of 10 bytes',
                Arr::get($span->getTags(), 'response_content')
            );

            return true;
        }));
    }

    /** @test */
    public function retrieve_current_span()
    {
        $tracer = $this->createTracer(new NoopReporter());

        $spanA = $tracer->startSpan('Span A');
        $spanB = $tracer->startSpan('Span A');

        $this->assertNotSame($spanA, $tracer->getCurrentSpan());
        $this->assertSame($spanB, $tracer->getCurrentSpan());
    }

    /** @test */
    public function reset_root_span_and_current_span_when_calling_flush()
    {
        $tracer = $this->createTracer(new NoopReporter());

        $span = $tracer->startSpan('Root Span');
        $rootSpanA = $tracer->getRootSpan();
        $this->assertSame($rootSpanA, $span);

        $tracer->flush();

        $this->assertNull($tracer->getRootSpan());
        $this->assertNull($tracer->getCurrentSpan());

        $span = $tracer->startSpan('Root Span B');
        $rootSpanB = $tracer->getRootSpan();
        $this->assertSame($rootSpanB, $span);
    }

    /** @test */
    public function reset_uuid_when_calling_flush()
    {
        $tracer = $this->createTracer(new NoopReporter());

        $tracer->startSpan('Example A');
        $uuidA = $tracer->getUUID();

        $tracer->flush();

        $tracer->startSpan('Example B');
        $uuidB = $tracer->getUUID();

        $this->assertNotEquals($uuidB, $uuidA);
    }

    /** @test */
    public function propagate_text_map()
    {
        $tracer = $this->createTracer(new NoopReporter());

        $tracer->startSpan('Example');

        $arr = $tracer->inject([], Formats::TEXT_MAP);

        $this->assertArrayHasKey('x-b3-traceid', $arr);
        $this->assertArrayHasKey('x-b3-spanid', $arr);
        $this->assertArrayHasKey('x-b3-sampled', $arr);

        $this->assertValidTraceContext($tracer->extract($arr, Formats::TEXT_MAP));
    }

    /** @test */
    public function propagate_psr_request()
    {
        $tracer = $this->createTracer(new NoopReporter());

        $tracer->startSpan('Example');

        $request = new PsrRequest('GET', 'http://example.com');

        /** @var PsrRequest $request */
        $request = $tracer->inject($request, Formats::PSR_REQUEST);

        $this->assertNotEmpty($request->getHeader('x-b3-traceid'));
        $this->assertNotEmpty($request->getHeader('x-b3-spanid'));
        $this->assertNotEmpty($request->getHeader('x-b3-sampled'));

        $this->assertValidTraceContext($tracer->extract($request, Formats::PSR_REQUEST));
    }

    /** @test */
    public function propagate_illuminate_http()
    {
        $tracer = $this->createTracer(new NoopReporter());

        $tracer->startSpan('Example');

        $request = new Request();

        /** @var Request $request */
        $request = $tracer->inject($request, Formats::ILLUMINATE_HTTP);

        $this->assertNotNull($request->header('x-b3-traceid'));
        $this->assertNotNull($request->header('x-b3-spanid'));
        $this->assertNotNull($request->header('x-b3-sampled'));

        $this->assertValidTraceContext($tracer->extract($request, Formats::ILLUMINATE_HTTP));
    }

    /** @test */
    public function propagate_amqp()
    {
        $tracer = $this->createTracer(new NoopReporter());

        $tracer->startSpan('Example');

        $message = new AMQPMessage();

        /** @var AMQPMessage $message */
        $message = $tracer->inject($message, Formats::AMQP);

        /** @var AMQPTable $amqpTable */
        $amqpTable = Arr::get($message->get_properties(), 'application_headers', new AMQPTable);
        $arr = $amqpTable->getNativeData();

        $this->assertArrayHasKey('x-b3-traceid', $arr);
        $this->assertArrayHasKey('x-b3-spanid', $arr);
        $this->assertArrayHasKey('x-b3-sampled', $arr);

        $this->assertValidTraceContext($tracer->extract($message, Formats::AMQP));
    }

    /** @test */
    public function propagate_google_pubsub()
    {
        $tracer = $this->createTracer(new NoopReporter());

        $tracer->startSpan('Example');

        $msgBody = $tracer->inject([
            'data' => '',
        ], Formats::GOOGLE_PUBSUB);

        $arr = Arr::get($msgBody, 'attributes', []);

        $this->assertArrayHasKey('x-b3-traceid', $arr);
        $this->assertArrayHasKey('x-b3-spanid', $arr);
        $this->assertArrayHasKey('x-b3-sampled', $arr);

        $this->assertValidTraceContext($tracer->extract(new Message($msgBody, []), Formats::GOOGLE_PUBSUB));
    }

    /** @test */
    public function propagate_vinelab_http()
    {
        $tracer = $this->createTracer(new NoopReporter());

        $tracer->startSpan('Example');

        $request = $tracer->inject([], Formats::VINELAB_HTTP);

        $this->assertMatchesRegularExpression('/x-b3-traceid: \w/', Arr::get($request, 'headers.0', ''));
        $this->assertMatchesRegularExpression('/x-b3-spanid: \w/', Arr::get($request, 'headers.1', ''));
        $this->assertMatchesRegularExpression('/x-b3-sampled: \d/', Arr::get($request, 'headers.2', ''));
    }

    /**
     * @return string
     */
    protected function getRegexpForUUID(): string
    {
        return '/[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}/';
    }

    /**
     * @param  SpanContext  $spanContext
     */
    protected function assertValidTraceContext(SpanContext $spanContext): void
    {
        $this->assertNotNull($spanContext);
        $this->assertInstanceOf(TraceContext::class, $spanContext->getRawContext());
    }

    protected function assertSpan($reporter, ZipkinTracer $tracer, Closure $assert = null)
    {
        $startTimestamp = intval(microtime(true) * 1000000);
        $span = $tracer->startSpan('Http Request', null, $startTimestamp);
        $span->setName('Create Order');
        $span->tag('request_path', 'api/orders');
        $span->annotate('Create Payment');
        $span->annotate('Update Order Status');
        $finishTimestamp = intval(microtime(true) * 1000000);
        $span->finish($finishTimestamp);
        $tracer->flush();

        if (!$assert) {
            $assert = function ($spans) use ($startTimestamp, $finishTimestamp) {
                /** @var Span $span */
                $span = $this->shiftSpan($spans);
                $this->assertEquals('Create Order', $span->getName());
                $this->assertEquals($finishTimestamp - $startTimestamp, $span->getDuration());
                $this->assertEquals('api/orders', Arr::get($span->getTags(), 'request_path'));
                $this->assertEquals('Create Payment', Arr::get($span->getAnnotations(), '0.value'));
                $this->assertEquals('Update Order Status', Arr::get($span->getAnnotations(), '1.value'));
            };
        }

        $reporter->shouldHaveReceived('report')->with(
            Mockery::on(function ($spans) use ($assert) {
                $assert($spans);
                return true;
            })
        );
    }
}
