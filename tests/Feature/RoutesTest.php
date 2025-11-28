<?php

/**
 * Feature tests asserting that Guardrails routes are registered.
 */
use Illuminate\Support\Facades\Route;

it('registers routes with names', function () {
    expect(Route::has('guardrails.api.index'))->toBeTrue();
});
