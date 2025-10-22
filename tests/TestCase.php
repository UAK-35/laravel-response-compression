<?php

declare(strict_types=1);

namespace Uak35\ResponseCompression\Tests;

use Uak35\ResponseCompression\ResponseCompressionServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('response-compression.enabled', true);
        $app['config']->set('response-compression.algorithm', 'gzip');
        $app['config']->set('response-compression.min_length', 1024);
        $app['config']->set('response-compression.gzip.level', 5);

        $app['config']->set('response-compression.cache.enabled', true);
        $app['config']->set('response-compression.cache.control.directive', 'public, max-age=31536000');
    }

    protected function getPackageProviders($app)
    {
        return [
            ResponseCompressionServiceProvider::class,
        ];
    }
}
