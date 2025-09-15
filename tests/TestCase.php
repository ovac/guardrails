<?php

namespace OVAC\Guardrails\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use OVAC\Guardrails\GuardrailsServiceProvider;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [GuardrailsServiceProvider::class];
    }

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
        $app['config']->set('guardrails.middleware', ['api','auth:web']);
        $app['config']->set('guardrails.web_middleware', ['web','auth:web']);
    }

    protected function defineDatabaseMigrations()
    {
        // Load package migrations and our test fixtures
        $this->loadMigrationsFrom(realpath(__DIR__.'/../database/migrations'));
        $this->loadMigrationsFrom(__DIR__.'/migrations');
    }
}
