<?php

use Illuminate\Support\Facades\Route;

/**
 * Web routes for the lightweight Guardrails review UI.
 */
$guard = (string) config('guardrails.auth.guard', config('auth.defaults.guard', 'web'));
$defaultWeb = ['web', 'auth:'.$guard];
$webMiddleware = (array) (config('guardrails.web_middleware') ?? $defaultWeb);
$pagePrefix = trim((string) config('guardrails.page_prefix', 'guardrails'), '/');

// Minimal blade UI that consumes the API (no route name to avoid collisions)
Route::middleware($webMiddleware)
    ->prefix($pagePrefix)
    ->group(function () {
        Route::view('/', 'guardrails::index');
    });
