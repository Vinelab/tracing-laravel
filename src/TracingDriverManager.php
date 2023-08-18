<?php

namespace Vinelab\Tracing;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Manager;
use InvalidArgumentException;
use Vinelab\Tracing\Contracts\Tracer;
use Vinelab\Tracing\Drivers\Null\NullTracer;
use Vinelab\Tracing\Drivers\Zipkin\ZipkinTracer;
use Zipkin\Samplers\BinarySampler;
use Zipkin\Samplers\PercentageSampler;

class TracingDriverManager extends Manager
{
    /**
     * @var Repository
     */
    protected $config;

    /**
     * EngineManager constructor.
     * @param $app
     */
    public function __construct($app)
    {
        parent::__construct($app);
        $this->config = $app->make('config');
    }

    /**
     * Get the default driver name.
     *
     * @return string
     */
    public function getDefaultDriver()
    {
        if (is_null($this->config->get('tracing.driver'))) {
            return 'null';
        }

        return $this->config->get('tracing.driver');
    }

    /**
     * Create an instance of Zipkin tracing engine
     *
     * @return ZipkinTracer|Tracer
     * @throws InvalidArgumentException
     */
    public function createZipkinDriver()
    {
        $tracer = new ZipkinTracer(
            $this->config->get('tracing.service_name'),
            $this->config->get('tracing.zipkin.host'),
            $this->config->get('tracing.zipkin.port'),
            $this->config->get('tracing.zipkin.options.128bit'),
            $this->config->get('tracing.zipkin.options.request_timeout', 5),
            null,
            $this->getZipkinSampler()
        );

        ZipkinTracer::setMaxTagLen(
            $this->config->get('tracing.zipkin.options.max_tag_len', ZipkinTracer::getMaxTagLen())
        );

        return $tracer->init();
    }

    public function createNullDriver()
    {
        return new NullTracer();
    }

    /**
     * @return BinarySampler|PercentageSampler
     * @throws InvalidArgumentException
     */
    protected function getZipkinSampler()
    {
        $samplerClassName = $this->config->get('tracing.zipkin.sampler_class');
        if (!class_exists($samplerClassName)) {
            throw new InvalidArgumentException(
                \sprintf('Invalid sampler class. Expected `BinarySampler` or `PercentageSampler`, got %f', $samplerClassName)
            );
        }

        switch ($samplerClassName) {
            case BinarySampler::class:
                $sampler = BinarySampler::createAsAlwaysSample();
                break;
            case PercentageSampler::class:
                $sampler = PercentageSampler::create($this->config->get('tracing.zipkin.percentage_sampler_rate'));
                break;
        }

        return $sampler;
    }
}
