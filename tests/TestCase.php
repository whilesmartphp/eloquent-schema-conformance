<?php

namespace Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use Whilesmart\SchemaConformance\SchemaConformanceServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [SchemaConformanceServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('cache.default', 'array');
    }
}
