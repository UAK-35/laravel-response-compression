<?php

namespace Chr15k\ResponseOptimizer;

use Illuminate\Support\ServiceProvider;

class ResponseOptimizerServiceProvider extends ServiceProvider
{
    public static string $abstract = 'response-optimizer';

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/response-optimizer.php',
            static::$abstract
        );
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/response-optimizer.php' => config_path(static::$abstract.'.php'),
            ], 'config');
        }
    }
}
