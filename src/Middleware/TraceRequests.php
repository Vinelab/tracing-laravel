<?php

namespace Vinelab\Tracing\Middleware;

use Closure;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
        $span->tag('request_headers', strval($request->headers));
        $span->tag('request_ip', $request->ip());

        if (in_array($request->headers->get('Content_Type'), $this->config->get('tracing.middleware.payload.content_types'))) {
            $span->tag('request_input', json_encode($request->input()));
        }
    }

    /**
     * @param  Span  $span
     * @param  Request  $request
     * @param  Response|JsonResponse $response
     */
    protected function tagResponseData(Span $span, Request $request, $response): void
    {
        if ($request->route()) {
            $span->tag('laravel_action', $request->route()->getActionName());
        }

        $span->tag('response_status', strval($response->getStatusCode()));
        $span->tag('response_headers', strval($response->headers));

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
}
