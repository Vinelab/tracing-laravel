> **Disclaimer**: This package is new and may be considered unstable. Use in production at your own risk.

# Laravel Tracing

- [Introduction](#introduction)
- [Requirements](#requirements)
- [Installation](#installation)
- [Driver Prerequisites](#driver-prerequisites)
  - [Zipkin](#zipkin)
  - [Jaeger](#jaeger)
- [Usage](#usage)
  - [Creating Spans](#creating-spans)
  - [Custominzing Spans](#customizing-spans)
  - [Retrieving Spans](#retrieving-spans)
  - [Controlling Spans](#controlling-spans)
  - [Flushing Spans](#flushing-spans)
  - [Logging Integration](#logging-integration)
  - [Middleware](#middleware)
  - [Console Commands](#console-commands)
  - [Context Propagation](#context-propagation)
- [Custom Drivers](#custom-drivers)
  - [Writing New Driver](#writing-new-driver)
  - [Registering New Driver](#registering-new-driver)

## Introduction

Distributed tracing is the process of tracking the activity resulting from a request to an application. With this feature, you can:

- Trace the path of a request as it travels across a complex system
- Discover the latency of the components along that path
- Know which component in the path is creating a bottleneck
- Inspect payloads that are being sent between components
- Build execution graph for each component internals and more

A distributed trace is composed of multiple spans, which represent time spent in services or resources of those services.

Each **Span** has the following:

- Operation name
- Start timestamp
- Finish timestamp
- Set of zero or more key:value tags to enable lookup and record additional information
- Set of zero or more logs paired with a timestamp
- References to related Spans (e.g. a parent)

Spans are typically displayed for your view as a time axis where each span can be unfolded to inpect additional details:

![image](https://i.gyazo.com/ee0065123c9d7536279e9e0f9ad60610.png)

The **Tracer** interface (available via `Trace` facade) creates Spans and understands how to Inject (serialize) and Extract (deserialize) them across process boundaries.

See [OpenTracing spec](https://opentracing.io/specification/) for more details on semantics behind distributed tracing.

## Requirements

This package requires **PHP >= 7.1** and **Laravel 5.4 or later**.

## Installation

First, install the package via Composer:

```sh
composer require vinelab/tracing-laravel
```

**Laravel 5.4** users will also need to add the service provider to the `providers` array in `config/app.php`:

```php
Vinelab\Tracing\TracingServiceProvider::class,
```

After installation, you can publish the package configuration using the `vendor:publish` command. This command will publish the `tracing.php` configuration file to your config directory:

```sh
php artisan vendor:publish --provider="Vinelab\Tracing\TracingServiceProvider"
```

You may configure the driver and service name in your `.env` file:

```sh
TRACING_DRIVER=zipkin
TRACING_SERVICE_NAME=orders
```

## Driver Prerequisites

### Zipkin

Use the following environment variables to configure Zipkin:

```sh
ZIPKIN_HOST=localhost
ZIPKIN_PORT=9411
```

### Jaeger

Jaeger is not officially supported because of the lack of official instrumentation for PHP. However, you can still post spans to Jaeger collector using [Zipkin compatible HTTP endpoint](https://www.jaegertracing.io/docs/1.11/features/#backwards-compatibility-with-zipkin).

---

The package also includes `null` driver that discards created spans.

## Usage

You will work with tracing via a `Trace` facade provided by this package.

### Creating Spans

Starting new trace is as simple as calling `startSpan` method with name for a logical operation the span represents:

```php
$span = Trace::startSpan('Create Order');
```

Often, you need to continue an existing trace which is why `startSpan` also accepts additional parameter for span context. **SpanContext** may be propagated via various channels including HTTP requests, AMQP messages, arrays or even another span:

```php
$spanContext = Trace::extract($request, Formats::ILLUMINATE_HTTP);

$rootSpan = Trace::startSpan('Create Order', $spanContext);

$childSpan = Trace::startSpan('Validate Order', $rootSpan->getContext())
```

The possibilities are limitless. Refer to [Context Propagation](#context-propagation) section for more details.

### Customizing Spans

Override span name:

```php
$span->setName('Create Order');
```

Add tags, which may be used as lookup keys (to search span on UI) or additional details:

```php
$span->tag('shipping_method', $shipping_method);
```

### Retrieving Spans

You can retrieve the current span, which is also your most recently created span:

```php
$span = Trace::getCurrentSpan()
```

The first span you create when processing a request in the service is called a root span (not to mix with the global root span of the trace):

> After you call [flush](#flushing-spans), the root span is reset.

```php
$span = Trace::getRootSpan()
```

### Controlling Spans

You may finish the span by calling `finish` on it. Span duration is derived by subtracting the start timestamp from this:

```php
$span->finish()
```

You can log additional data between span start and finish. For example, `annotate` creates a time-stamped event to explain latencies:

```php
$span->annotate('Order Validated')
```

## Flushing Spans

Flush refers to the process of sending all pending spans to the transport. It will also reset the state of the tracer including the active spans and UUID:

```php
Trace::flush()
```

Most of the time though you don't need to explicitly call `flush`.  Since PHP is designed to die after each request, we already handle finishing the root span and calling flush upon application shutdown for you.

It's only when processing requests continuously in a loop (e.g. AMQP channels) that you must resort to calling `flush` manually.

### Logging Integration

Each root span is associated with a unique identifier that can be used to lookup its trace. It is recommended you include it as part of [context](https://github.com/laravel/framework/blob/v5.8.31/src/Illuminate/Foundation/Exceptions/Handler.php#L151) when reporting errors to bridge the gap between different parts of your monitoring stack:

```php
// Illuminate\Foundation\Exceptions\Handler

/**
  * Get the default context variables for logging.
  *
  * @return array
  */
protected function context()
{
    return array_filter([
        'userId' => Auth::id(),
        'uuid' => Trace::getUUID(),
    ]);
}
```

[Custom drivers](#custom-drivers) may also support logging structured data (not available in Zipkin) which can be used for integrating tracing with a Log facade:

```php
use Illuminate\Support\Facades\Event;

/**
 * Bootstrap any application services.
 *
 * @return void
 */
public function boot()
{
    Event::listen(MessageLogged::class, function (MessageLogged $e) {
        Tracer::getCurrentSpan()->log((array) $e);
    });
}
```

### Middleware

This package includes a `\Vinelab\Tracing\Middleware\TraceRequests` middleware to take care of continuing the trace from incoming HTTP request.

You should register middleware class in the `$middleware` property of your `app/Http/Kernel.php` class.

The middleware adds the following **tags** on a root span:
- `type` (http)
- `request_method`
- `request_path`
- `request_uri`
- `request_headers`
- `request_ip`
- `request_input`
- `response_status`
- `response_headers`
- `response_content`

> Request and response body are only included for whitelisted content-types. See `logging.content_types` option in your `config/tracing.php`.

You can override the default name of the span in the controller:

```php
Trace::getRootSpan()->setName('Create Order')
```

### Console Commands

If you want to trace select console commands, make them implement `Vinelab\Tracing\Contracts\ShouldBeTraced` interface, indicating that we should start spans for the command.

The trace will include the following **tags** on a root span:

- `type` (cli)
- `argv`

You can override the default name of the span in the command itself:

```php
Trace::getRootSpan()->setName('Mark Orders Expired')
```

### Context Propagation

As we talked about previously, the tracer understands how to inject and extract trace context across different applications (services).

We have already seen the example of extracting trace from HTTP request:

```php
$spanContext = Trace::extract($request, Formats::ILLUMINATE_HTTP);
```

Of course, you don't need to do this manually because this package already includes a [middleware](#middleware) to handle this for you, but the trace may not necessarily come from HTTP request.

The second parameter is a format descriptor that tells us how to deserialize tracing headers from given carrier. By default, the following formats are supported:

```php
use Vinelab\Tracing\Propagation\Formats;

$spanContext = Trace::extract($carrier, Formats::TEXT_MAP);
$spanContext = Trace::extract($carrier, Formats::PSR_REQUEST);
$spanContext = Trace::extract($carrier, Formats::ILLUMINATE_HTTP);
$spanContext = Trace::extract($carrier, Formats::AMQP);
```

You may also add your own format using `registerExtractionFormat` method.

```php
Trace::registerExtractionFormat("pubsub", new PubSubExtractor());
```

The injection format must implement `Vinelab\Tracing\Contracts\Extractor`. Refer to default Zipkin implementation for example.

```php
interface Extractor
{
    public function extract($carrier): ?SpanContext;
}
```

Naturally, you can also inject existing trace context from the **current span** into a given carrier so that another service can continue the trace:

```php
$message = Trace::inject($message, Formats::AMQP);

$channel->basic_publish($message, $this->exchangeName, $routingKey);
```

By default, the following formats are supported:

```php
use Vinelab\Tracing\Propagation\Formats;

$carrier = Trace::inject($carrier, Formats::TEXT_MAP);
$carrier = Trace::inject($carrier, Formats::PSR_REQUEST);
$carrier = Trace::inject($carrier, Formats::ILLUMINATE_HTTP);
$carrier = Trace::inject($carrier, Formats::AMQP);
$carrier = Trace::inject($carrier, Formats::VINELAB_HTTP);
```

You may also add your own format using `registerInjectionFormat` method.

The injection format must implement `Vinelab\Tracing\Contracts\Injector`. Refer to default Zipkin implementation for example.

```php
interface Injector
{
    public function inject(SpanContext $spanContext, &$carrier): void;
}
```

You can also use `injectContext` method if you need to pass span context explicitly:

```php
$carrier = Trace::injectContext($carrier, Formats::TEXT_MAP, $span->getContext());
```

---
**IMPORTANT**: You don't need to create a custom propagation format if you need to get something done quickly. You can always avail of the default `TEXT_MAP` format to inject or extract tracing headers from an associative array.

## Custom Drivers

### Writing New Driver

New drivers must adhere to `Vinelab\Tracing\Contracts\Tracer` contract. Refer to the default ZipkinTracer imlementation for example.

```php
use Vinelab\Tracing\Contracts\Extractor;
use Vinelab\Tracing\Contracts\Injector;
use Vinelab\Tracing\Contracts\Span;
use Vinelab\Tracing\Contracts\SpanContext;

public function startSpan(string $name, SpanContext $spanContext = null): Span;
public function getRootSpan(): ?Span;
public function getCurrentSpan(): ?Span;
public function getUUID(): ?string;
public function extract($carrier, string $format): ?SpanContext;
public function inject($carrier, string $format);
public function injectContext($carrier, string $format, SpanContext $spanContext);
public function registerExtractionFormat(string $format, Extractor $extractor): array;
public function registerInjectionFormat(string $format, Injector $injector): array;
public function flush(): void;
```

### Registering New Driver

Once you have written your custom driver, you may register it using the extend method of the `TracingDriverManager`. You should call the `extend` method from the `boot` method of your `AppServiceProvider` or any other service provider used by your application. For example, if you have written a `JaegerTracer`, you may register it like so:

```php
use Vinelab\Tracing\TracingDriverManager;

/**
 * Bootstrap any application services.
 *
 * @return void
 */
public function boot()
{
    resolve(TracingDriverManager::class)->extend('jaeger', function () {
        return new JaegerTracer;
    });
}
```

Once your driver has been registered, you may specify it as your tracing driver in your environment variables:

```
TRACING_DRIVER=jaeger
```