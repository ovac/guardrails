<?php

/**
 * Feature tests validating Guardrails service provider bootstrapping.
 */
use Illuminate\Support\Facades\Route;

it('merges config and registers routes', function () {
    expect(config('guardrails.route_prefix'))->toBeString();
    expect(Route::has('guardrails.index'))->toBeTrue();
});
