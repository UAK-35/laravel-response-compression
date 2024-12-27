<?php

declare(strict_types=1);

namespace Chr15k\ResponseOptimizer\Tests;

use Chr15k\ResponseOptimizer\ResponseOptimizerServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('response-optimizer.compression.enabled', true);
        $app['config']->set('response-optimizer.compression.algorithm', 'gzip');
        $app['config']->set('response-optimizer.compression.min_length', 1024);
        $app['config']->set('response-optimizer.compression.gzip.level', 5);

        $app['config']->set('response-optimizer.cache.enabled', true);
        $app['config']->set('response-optimizer.cache.control.directive', 'public, max-age=31536000');
    }

    protected function getPackageProviders($app)
    {
        return [
            ResponseOptimizerServiceProvider::class,
        ];
    }
}
