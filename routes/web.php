<?php

use Illuminate\Support\Facades\Route;

$webMiddleware = (array) config('guardrails.web_middleware', ['web','auth:staff']);
$pagePrefix = trim((string) config('guardrails.page_prefix', 'staff/guardrails'), '/');

// Minimal blade UI that consumes the API
Route::middleware($webMiddleware)
    ->prefix($pagePrefix)
    ->group(function () {
        Route::view('/', 'guardrails::index')->name('guardrails.index');
    });
