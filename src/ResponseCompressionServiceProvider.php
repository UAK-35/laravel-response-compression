<?php

declare(strict_types=1);

namespace Uak35\ResponseCompression;

use Illuminate\Support\ServiceProvider;
use Override;
use Uak35\ResponseCompression\Support\Config;

final class ResponseCompressionServiceProvider extends ServiceProvider
{
    public static string $abstract = 'response-compression';

    public function getConfigPath(): string
    {
        return sprintf('%s/../config/%s.php', __DIR__, self::$abstract);
    }

    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom($this->getConfigPath(), self::$abstract);
    }

    /**
     * Every configured value this package reads is read here, once, so a value it cannot
     * read stops the application at boot instead of on whichever request first needs it.
     *
     * That ordering is the whole point: a bad `min_length` used to reach the middleware as
     * a floor of 0 and be discovered by nobody, and the same value is far easier to
     * diagnose as a start-up failure than as a wrong response body.
     */
    public function boot(): void
    {
        Config::validate();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                $this->getConfigPath() => config_path(self::$abstract.'.php'),
            ], self::$abstract.'-config');
        }
    }
}
