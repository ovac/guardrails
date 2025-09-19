<?php

namespace OVAC\Guardrails\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use OVAC\Guardrails\GuardrailsServiceProvider;

/**
 * Base TestCase for Guardrails package feature and unit tests.
 */
class TestCase extends Orchestra
{
    /**
     * Register the package service providers used during tests.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<int, class-string<\Illuminate\Support\ServiceProvider>>
     */
    protected function getPackageProviders($app)
    {
        return [GuardrailsServiceProvider::class];
    }

    /**
     * Configure the application environment for the test suite.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function defineEnvironment($app): void
    {
        // Minimal app config
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        // In-memory sqlite
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Auth guard set to web using our test user model
        $app['config']->set('auth.guards.web', [
            'driver' => 'session',
            'provider' => 'users',
        ]);
        $app['config']->set('auth.providers.users', [
            'driver' => 'eloquent',
            'model' => \OVAC\Guardrails\Tests\Fixtures\User::class,
        ]);
        $app['config']->set('auth.defaults.guard', 'web');

        // Configure package to use web guard and simple middleware
        $app['config']->set('guardrails.auth.guard', 'web');
        $app['config']->set('guardrails.middleware', ['api', 'auth:web']);
        $app['config']->set('guardrails.web_middleware', ['web', 'auth:web']);
        $app['config']->set('guardrails.permissions.view', '');
        $app['config']->set('guardrails.permissions.sign', '');
    }

    /**
     * Register the database migrations required for the in-memory sqlite setup.
     *
     * @return void
     */
    protected function defineDatabaseMigrations()
    {
        // Load package migrations and our test fixtures
        $this->loadMigrationsFrom(realpath(__DIR__.'/../database/migrations'));
        $this->loadMigrationsFrom(__DIR__.'/migrations');
    }
}
