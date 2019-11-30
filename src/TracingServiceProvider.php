<?php

namespace Vinelab\Tracing;

use Illuminate\Console\Events\CommandStarting;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\App;
use Illuminate\Support\ServiceProvider;
use Vinelab\Tracing\Contracts\Tracer;
use Vinelab\Tracing\Facades\Trace;
use Vinelab\Tracing\Listeners\TraceCommand;

class TracingServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/tracing.php' => config_path('tracing.php'),
            ]);
        }

        $this->app['events']->listen(CommandStarting::class, TraceCommand::class);

        $this->app['events']->listen(
            JobProcessing::class,
            'Vinelab\Tracing\Listeners\QueueJobSubscriber@onJobProcessing'
        );
        $this->app['events']->listen(
            JobProcessed::class,
            'Vinelab\Tracing\Listeners\QueueJobSubscriber@onJobProcessed'
        );
        $this->app['events']->listen(
            JobFailed::class,
            'Vinelab\Tracing\Listeners\QueueJobSubscriber@onJobFailed'
        );

        if ($this->app['config']['tracing.errors']) {
            $this->app['events']->listen(MessageLogged::class, function (MessageLogged $event) {
                if ($event->level == 'error') {
                    optional(Trace::getRootSpan())->tag('error', 'true');
                }
            });
        }

        $this->app->terminating(function () {
            optional(Trace::getRootSpan())->finish();
            Trace::flush();
        });
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom( dirname(__DIR__).'/config/tracing.php', 'tracing');

        $this->app->singleton(TracingDriverManager::class, function ($app) {
            return new TracingDriverManager($app);
        });

        $this->app->singleton(Tracer::class, function ($app) {
            return $app->make(TracingDriverManager::class)->driver($this->app['config']['tracing.driver']);
        });
    }
}
