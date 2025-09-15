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
        // Keep defaults minimal for route registration assertions
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    }
}

