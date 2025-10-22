<?php

declare(strict_types=1);

namespace Uak35\ResponseCompression;

use Illuminate\Support\ServiceProvider;

final class ResponseCompressionServiceProvider extends ServiceProvider
{
    public static string $abstract = 'response-compression';

    public function getConfigPath(): string
    {
        return sprintf('%s/../config/%s.php', __DIR__, self::$abstract);
    }

    #[\Override]
    public function register(): void
    {
        $this->mergeConfigFrom($this->getConfigPath(), self::$abstract);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                $this->getConfigPath() => config_path(self::$abstract.'.php'),
            ], self::$abstract.'-config');
        }
    }
}
