<?php

use Illuminate\Support\Facades\Route;
use OVAC\Guardrails\Http\Controllers\GuardrailApprovalsController;

/**
 * API routes that expose Guardrails approval endpoints.
 *
 * @noinspection PhpUndefinedFunctionInspection Laravel helper functions are resolved at runtime.
 */
$prefix = trim((string) config('guardrails.route_prefix', 'guardrails/api'), '/');
$guard = (string) config('guardrails.auth.guard', config('auth.defaults.guard', 'web'));
$defaultMiddleware = ['api', 'auth:'.$guard];
$middleware = (array) (config('guardrails.middleware') ?? $defaultMiddleware);

$router = app('router');
$aliases = method_exists($router, 'getMiddleware') ? $router->getMiddleware() : [];
$hasAbilities = array_key_exists('abilities', $aliases);

// JSON API routes for Guardrails approval endpoints
Route::prefix($prefix)
    ->middleware($middleware)
    ->as('guardrails.')
    ->group(function () use ($hasAbilities) {
        $indexMw = [];
        $viewAbility = (string) config('guardrails.permissions.view', 'approvals.manage');
        if ($hasAbilities && $viewAbility !== '') {
            $indexMw[] = 'abilities:'.$viewAbility;
        }
        Route::get('', [GuardrailApprovalsController::class, 'index'])
            ->middleware($indexMw)
            ->name('index');

        $approveMw = [];
        $signAbility = (string) config('guardrails.permissions.sign', 'approvals.manage');
        if ($hasAbilities && $signAbility !== '') $approveMw[] = 'abilities:'.$signAbility;
        Route::post('{request}/steps/{step}/approve', [GuardrailApprovalsController::class, 'approveStep'])
            ->middleware($approveMw)
            ->name('steps.approve');
    });
