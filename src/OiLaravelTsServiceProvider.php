<?php

namespace OiLab\OiLaravelTs;

use OiLab\OiLaravelTs\Console\Commands\GenerateTypescriptCommand;
use Illuminate\Support\ServiceProvider;

class OiLaravelTsServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/Config/oi-laravel-ts.php',
            'oi-laravel-ts'
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateTypescriptCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/Config/oi-laravel-ts.php' => config_path('oi-laravel-ts.php'),
            ], 'oi-laravel-ts-config');
        }
    }
}
