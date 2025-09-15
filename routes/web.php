<?php

use Illuminate\Support\Facades\Route;

$guard = (string) config('guardrails.auth.guard', 'staff');
$defaultWeb = ['web','auth:'.$guard];
$webMiddleware = (array) (config('guardrails.web_middleware') ?? $defaultWeb);
$pagePrefix = trim((string) config('guardrails.page_prefix', 'staff/guardrails'), '/');

// Minimal blade UI that consumes the API
Route::middleware($webMiddleware)
    ->prefix($pagePrefix)
    ->group(function () {
        Route::view('/', 'guardrails::index')->name('guardrails.index');
    });
