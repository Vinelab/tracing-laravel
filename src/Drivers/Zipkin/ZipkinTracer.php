<?php

namespace Vinelab\Tracing\Drivers\Zipkin;

use Illuminate\Support\Arr;
use Ramsey\Uuid\Uuid;
use Vinelab\Tracing\Contracts\Extractor;
use Vinelab\Tracing\Contracts\Injector;
use Vinelab\Tracing\Contracts\Span;
use Vinelab\Tracing\Contracts\SpanContext;
use Vinelab\Tracing\Contracts\Tracer;
use Vinelab\Tracing\Drivers\Zipkin\Extractors\AMQPExtractor;
use Vinelab\Tracing\Drivers\Zipkin\Extractors\GooglePubSubExtractor;
use Vinelab\Tracing\Drivers\Zipkin\Extractors\IlluminateHttpExtractor;
use Vinelab\Tracing\Drivers\Zipkin\Extractors\PsrRequestExtractor;
use Vinelab\Tracing\Drivers\Zipkin\Extractors\TextMapExtractor;
use Vinelab\Tracing\Drivers\Zipkin\Extractors\ZipkinExtractor;
use Vinelab\Tracing\Drivers\Zipkin\Injectors\AMQPInjector;
use Vinelab\Tracing\Drivers\Zipkin\Injectors\GooglePubSubInjector;
use Vinelab\Tracing\Drivers\Zipkin\Injectors\IlluminateHttpInjector;
use Vinelab\Tracing\Drivers\Zipkin\Injectors\PsrRequestInjector;
use Vinelab\Tracing\Drivers\Zipkin\Injectors\TextMapInjector;
use Vinelab\Tracing\Drivers\Zipkin\Injectors\VinelabHttpInjector;
use Vinelab\Tracing\Drivers\Zipkin\Injectors\ZipkinInjector;
use Vinelab\Tracing\Exceptions\UnregisteredFormatException;
use Vinelab\Tracing\Exceptions\UnresolvedCollectorIpException;
use Vinelab\Tracing\Propagation\Formats;
use Zipkin\Endpoint;
use Zipkin\Reporter;
use Zipkin\Reporters\Http as HttpReporter;
use Zipkin\Sampler;
use Zipkin\Samplers\BinarySampler;
use Zipkin\TracingBuilder;

class ZipkinTracer implements Tracer
{
    /**
     * @var int
     */
    protected static $maxTagLen = 1048576;

    /**
     * @var string
     */
    protected $serviceName;

    /**
     * @var string
     */
    protected $host;

    /**
     * @var int
     */
    protected $port;

    /**
     * @var bool
     */
    protected $usesTraceId128bits;

    /**
     * @var int|null
     */
    protected $requestTimeout;

    /**
     * @var Reporter|null
     */
    protected $reporter;

    /**
     * @var \Zipkin\DefaultTracing|\Zipkin\Tracing
     */
    protected $tracing;

    /**
     * @var Span|null
     */
    protected $rootSpan;

    /**
     * @var Span|null
     */
    protected $currentSpan;

    /**
     * @var string|null
     */
    protected $uuid;

    /**
     * @var array
     */
    protected $extractionFormats = [];

    /**
     * @var array
     */
    protected $injectionFormats = [];

    /**
     * ZipkinTracer constructor.
     * @param  string  $serviceName
     * @param  string  $host
     * @param  int  $port
     * @param  bool|null  $usesTraceId128bits
     * @param  int|null  $requestTimeout
     * @param  Reporter|null  $reporter
     */
    public function __construct(
        string $serviceName,
        string $host,
        int $port,
        ?bool $usesTraceId128bits = false,
        ?int $requestTimeout = 5,
        ?Reporter $reporter = null
    ) {
        $this->serviceName = $serviceName;
        $this->host = $host;
        $this->port = $port;
        $this->usesTraceId128bits = $usesTraceId128bits;
        $this->requestTimeout = $requestTimeout;
        $this->reporter = $reporter;
    }

    /**
     * @return int
     */
    public static function getMaxTagLen(): int
    {
        return self::$maxTagLen;
    }

    /**
     * @param  int  $maxTagLen
     */
    public static function setMaxTagLen(int $maxTagLen): void
    {
        self::$maxTagLen = $maxTagLen;
    }

    /**
     * Initialize tracer based on parameters provided during object construction
     *
     * @return Tracer
     */
    public function init(): Tracer
    {
        $this->tracing = TracingBuilder::create()
            ->havingLocalEndpoint($this->createEndpoint())
            ->havingTraceId128bits($this->usesTraceId128bits)
            ->havingSampler($this->createSampler())
            ->havingReporter($this->createReporter())
            ->build();

        $this->registerDefaultExtractionFormats();
        $this->registerDefaultInjectionFormats();

        return $this;
    }

    /**
     * Start a new span based on a parent trace context. The context may come either from
     * external source (extracted from HTTP request, AMQP message, etc., see extract method)
     * or received from another span in this service.
     *
     * If parent context does not contain a trace, a new trace will be implicitly created.
     *
     * The first span you create in the service will be considered the root span. Calling
     * flush {@see ZipkinTracer::flush()} will unset the root span along with request uuid.
     *
     * @param  string  $name
     * @param  SpanContext|null  $spanContext
     * @return Span
     */
    public function startSpan(string $name, ?SpanContext $spanContext = null): Span
    {
        $rawSpan = $this->tracing->getTracer()->nextSpan($spanContext ? $spanContext->getRawContext() : null);

        if ($this->rootSpan) {
            $span = new ZipkinSpan($rawSpan, false);
        } else {
            $span = new ZipkinSpan($rawSpan, true);
            $this->rootSpan = $span;

            $this->uuid = Uuid::uuid1()->toString();
            $span->tag('uuid', $this->uuid);
        }

        $this->currentSpan = $span;
        $span->setName($name);

        $rawSpan->start();

        return $span;
    }

    /**
     * Retrieve the root span of the service
     *
     * @return Span|null
     */
    public function getRootSpan(): ?Span
    {
        return $this->rootSpan;
    }

    /**
     * Retrieve the most recently activated span.
     *
     * @return Span|null
     */
    public function getCurrentSpan(): ?Span
    {
        return $this->currentSpan;
    }

    /**
     * @return string
     */
    public function getUUID(): ?string
    {
        return $this->uuid;
    }

    /**
     * Extract span context given the format that tells tracer how to decode the carrier
     *
     * @param mixed $carrier
     * @param  string  $format
     * @return SpanContext|null
     */
    public function extract($carrier, string $format): ?SpanContext
    {
        return $this->resolveExtractor($format)
            ->setPropagation($this->tracing->getPropagation())
            ->extract($carrier);
    }

    /**
     * Implicitly inject current span context using the format descriptor that
     * tells how to encode trace info in the carrier parameters
     *
     * @param  mixed $carrier
     * @param  string  $format
     * @return mixed $carrier
     */
    public function inject($carrier, string $format)
    {
        $span = $this->getCurrentSpan();

        if ($span) {
            $this->resolveInjector($format)
                ->setPropagation($this->tracing->getPropagation())
                ->inject($span->getContext(), $carrier);
        }

        return $carrier;
    }

    /**
     * Inject specified span context into a given carrier using the format descriptor
     * that tells how to encode trace info in the carrier parameters
     *
     * @param  mixed $carrier
     * @param  string  $format
     * @param  SpanContext  $spanContext
     * @return mixed $carrier
     */
    public function injectContext($carrier, string $format, SpanContext $spanContext)
    {
        $this->resolveInjector($format)
            ->setPropagation($this->tracing->getPropagation())
            ->inject($spanContext, $carrier);

        return $carrier;
    }

    /**
     * Register extractor for new format
     *
     * @param  string  $format
     * @param  Extractor  $extractor
     * @return array
     */
    public function registerExtractionFormat(string $format, Extractor $extractor): array
    {
        return Arr::set($this->extractionFormats, $format, $extractor);
    }

    /**
     * Register injector for new format
     *
     * @param  string  $format
     * @param  Injector  $injector
     * @return array
     */
    public function registerInjectionFormat(string $format, Injector $injector): array
    {
        return Arr::set($this->injectionFormats, $format, $injector);
    }

    /**
     * Calling this will flush any pending spans to the transport and reset the state of the tracer.
     * Make sure this method is called after the request is finished.
     */
    public function flush(): void
    {
        $this->tracing->getTracer()->flush();
        $this->rootSpan = null;
        $this->currentSpan = null;
        $this->uuid = null;
    }

    /**
     * @return Reporter
     */
    protected function createReporter(): Reporter
    {
        if (!$this->reporter) {
            return new HttpReporter(null, [
                'endpoint_url' => sprintf('http://%s:%s/api/v2/spans', $this->host, $this->port),
                'timeout' => $this->requestTimeout,
            ]);
        }

        return $this->reporter;
    }

    /**
     * @return Endpoint
     */
    protected function createEndpoint(): Endpoint
    {
        if (strpos($this->host, ":") === false) {
            $ipv4 = filter_var($this->host, FILTER_VALIDATE_IP) ? $this->host : $this->resolveCollectorIp($this->host);

            return Endpoint::create($this->serviceName, $ipv4, null, $this->port);
        }

        return Endpoint::create($this->serviceName, null, $this->host, $this->port);
    }

    /**
     * @return Sampler
     */
    protected function createSampler(): Sampler
    {
        return BinarySampler::createAsAlwaysSample();
    }

    protected function registerDefaultExtractionFormats(): void
    {
        $this->registerExtractionFormat(Formats::TEXT_MAP, new TextMapExtractor());
        $this->registerExtractionFormat(Formats::PSR_REQUEST, new PsrRequestExtractor());
        $this->registerExtractionFormat(Formats::ILLUMINATE_HTTP, new IlluminateHttpExtractor());
        $this->registerExtractionFormat(Formats::AMQP, new AMQPExtractor());
        $this->registerExtractionFormat(Formats::GOOGLE_PUBSUB, new GooglePubSubExtractor());
    }

    protected function registerDefaultInjectionFormats(): void
    {
        $this->registerInjectionFormat(Formats::TEXT_MAP, new TextMapInjector());
        $this->registerInjectionFormat(Formats::PSR_REQUEST, new PsrRequestInjector());
        $this->registerInjectionFormat(Formats::ILLUMINATE_HTTP, new IlluminateHttpInjector());
        $this->registerInjectionFormat(Formats::AMQP, new AMQPInjector());
        $this->registerInjectionFormat(Formats::VINELAB_HTTP, new VinelabHttpInjector());
        $this->registerInjectionFormat(Formats::GOOGLE_PUBSUB, new GooglePubSubInjector());
    }

    /**
     * @param  string  $format
     * @return ZipkinInjector
     */
    protected function resolveInjector(string $format): ZipkinInjector
    {
        $injector = Arr::get($this->injectionFormats, $format);

        if (!$injector) {
            throw new UnregisteredFormatException("No injector registered for format $format");
        }

        return $injector;
    }

    /**
     * @param  string  $format
     * @return ZipkinExtractor
     */
    protected function resolveExtractor(string $format): ZipkinExtractor
    {
        $extractor = Arr::get($this->extractionFormats, $format);

        if (!$extractor) {
            throw new UnregisteredFormatException("No extractor registered for format $format");
        }

        return $extractor;
    }

    /**
     * @param  string  $host
     * @return string
     */
    protected function resolveCollectorIp(string $host): string
    {
        $ipv4 = gethostbyname($host);

        if ($ipv4 == $host) {
            $e = new UnresolvedCollectorIpException("Unable to resolve collector's IP address from hostname $host");

            app('log')->debug($e->getMessage(), ['exception' => $e]);

            return "127.0.0.1";
        }

        return $ipv4;
    }
}
