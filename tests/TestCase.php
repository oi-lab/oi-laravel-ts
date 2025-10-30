<?php

namespace OiLab\OiLaravelTs\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use OiLab\OiLaravelTs\OiLaravelTsServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app): array
    {
        return [
            OiLaravelTsServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        // Configure test environment
        $app['config']->set('oi-laravel-ts.output_path', storage_path('app/test-interfaces.ts'));
        $app['config']->set('oi-laravel-ts.with_counts', true);
        $app['config']->set('oi-laravel-ts.with_json_ld', false);
        $app['config']->set('oi-laravel-ts.save_schema', false);
    }
}
