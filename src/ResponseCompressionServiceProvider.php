<?php

namespace Chr15k\ResponseCompression;

use Illuminate\Support\ServiceProvider;

class ResponseCompressionServiceProvider extends ServiceProvider
{
    public static string $abstract = 'response-compression';

    public function getConfigPath(): string
    {
        return sprintf('%s/../config/%s.php', __DIR__, static::$abstract);
    }

    public function register(): void
    {
        $this->mergeConfigFrom($this->getConfigPath(), static::$abstract);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                $this->getConfigPath() => config_path(static::$abstract.'.php'),
            ], static::$abstract.'-config');
        }
    }
}
