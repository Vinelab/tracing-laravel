<?php

namespace Vinelab\Tracing\Middleware;

use Closure;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderBag;
use Vinelab\Http\Response;
use Vinelab\Tracing\Contracts\Span;
use Vinelab\Tracing\Contracts\Tracer;
use Vinelab\Tracing\Propagation\Formats;

class TraceRequests
{
    /**
     * @var Tracer
     */
    protected $tracer;

    /**
     * @var Repository
     */
    private $config;

    /**
     * TraceRequests constructor.
     * @param  Tracer  $tracer
     * @param  Repository  $config
     */
    public function __construct(Tracer $tracer, Repository $config)
    {
        $this->tracer = $tracer;
        $this->config = $config;
    }

    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @param  Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if (!$this->shouldBeExcluded($request->path())) {
            $spanContext = $this->tracer->extract($request, Formats::ILLUMINATE_HTTP);

            $span = $this->tracer->startSpan('Http Request', $spanContext);

            $this->tagRequestData($span, $request);
        }

        return $next($request);
    }

    /**
     * @param  Request  $request
     * @param  Response|JsonResponse  $response
     */
    public function terminate(Request $request, $response)
    {
        $span = $this->tracer->getRootSpan();

        if ($span) {
            $this->tagResponseData($span, $request, $response);

            $route = $request->route();

            if ($this->isLaravelRoute($route)) {
                $span->setName(sprintf('%s %s', $request->method(), $request->route()->uri()));
            }

            if ($this->isLumenRoute($route)) {
                $routeUri = $this->getLumenRouteUri($request->path(), $route[2]);
                $span->setName(sprintf('%s %s', $request->method(), $routeUri));
            }
        }
    }

    /**
     * @param  Request  $request
     * @param  Span  $span
     */
    protected function tagRequestData(Span $span, Request $request): void
    {
        $span->tag('type', 'http');
        $span->tag('request_method', $request->method());
        $span->tag('request_path', $request->path());
        $span->tag('request_uri', $request->getRequestUri());
        $span->tag('request_headers', $this->transformedHeaders($this->filterHeaders($request->headers)));
        $span->tag('request_ip', $request->ip());

        if (in_array($request->headers->get('Content_Type'), $this->config->get('tracing.middleware.payload.content_types'))) {
            $span->tag('request_input', json_encode($this->filterInput($request->input())));
        }
    }

    /**
     * @param  Span  $span
     * @param  Request  $request
     * @param  Response|JsonResponse $response
     */
    protected function tagResponseData(Span $span, Request $request, $response): void
    {
        if ($route = $request->route()) {
            if (method_exists($route, 'getActionName')) {
                $span->tag('laravel_action', $route->getActionName());
            }
        }

        $span->tag('response_status', strval($response->getStatusCode()));
        $span->tag('response_headers', $this->transformedHeaders($this->filterHeaders($response->headers)));

        if (in_array($response->headers->get('Content_Type'), $this->config->get('tracing.middleware.payload.content_types'))) {
            $span->tag('response_content', $response->content());
        }
    }

    /**
     * @param  string  $path
     * @return bool
     */
    protected function shouldBeExcluded(string $path): bool
    {
        foreach ($this->config->get('tracing.middleware.excluded_paths') as $excludedPath) {
            if (Str::is($excludedPath, $path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  HeaderBag  $headers
     * @return HeaderBag
     */
    protected function filterHeaders(HeaderBag $headers): array
    {
        return $this->hideSensitiveHeaders($this->filterAllowedHeaders(collect($headers)))->all();
    }

    /**
     * @param  Collection  $headers
     * @return Collection
     */
    protected function filterAllowedHeaders(Collection $headers): Collection
    {
        $allowedHeaders = $this->config->get('tracing.middleware.allowed_headers');

        if (in_array('*', $allowedHeaders)) {
            return $headers;
        }

        $normalizedHeaders = array_map('strtolower', $allowedHeaders);

        return $headers->filter(function ($value, $name) use ($normalizedHeaders) {
            return in_array($name, $normalizedHeaders);
        });
    }

    protected function hideSensitiveHeaders(Collection $headers): Collection
    {
        $sensitiveHeaders = $this->config->get('tracing.middleware.sensitive_headers');

        $normalizedHeaders = array_map('strtolower', $sensitiveHeaders);

        $headers->transform(function ($value, $name) use ($normalizedHeaders) {
            return in_array($name, $normalizedHeaders)
                ? ['This value is hidden because it contains sensitive info']
                : $value;
        });

        return $headers;
    }

    /**
     * @param  array  $headers
     * @return string
     */
    protected function transformedHeaders(array $headers = []): string
    {
        if (!$headers) {
            return '';
        }

        ksort($headers);
        $max = max(array_map('strlen', array_keys($headers))) + 1;

        $content = '';
        foreach ($headers as $name => $values) {
            $name = implode('-', array_map('ucfirst', explode('-', $name)));

            foreach ($values as $value) {
                $content .= sprintf("%-{$max}s %s\r\n", $name.':', $value);
            }
        }

        return $content;
    }

    /**
     * @param  array  $input
     * @return array
     */
    protected function filterInput(array $input = []): array
    {
        return $this->hideSensitiveInput(collect($input))->all();
    }

    /**
     * @param  Collection  $input
     * @return Collection
     */
    protected function hideSensitiveInput(Collection $input): Collection
    {
        $sensitiveInput = $this->config->get('tracing.middleware.sensitive_input');

        $normalizedInput = array_map('strtolower', $sensitiveInput);

        $input->transform(function ($value, $name) use ($normalizedInput) {
            return in_array($name, $normalizedInput)
                ? ['This value is hidden because it contains sensitive info']
                : $value;
        });

        return $input;
    }

    /**
     * @param $route
     * @return bool
     */
    protected function isLaravelRoute($route): bool
    {
        return $route && method_exists($route, 'uri');
    }

    /**
     * @param $route
     * @return bool
     */
    protected function isLumenRoute($route): bool
    {
        return is_array($route) && is_array($route[2]);
    }

    /**
     * @param  string  $path
     * @param  array  $parameters
     * @return string
     */
    protected function getLumenRouteUri(string $path, array $parameters): string
    {
        $replaceMap = array_combine(
            array_values($parameters),
            array_map(function ($v) { return '{'.$v.'}'; }, array_keys($parameters))
        );

        return strtr($path, $replaceMap);
    }
}
