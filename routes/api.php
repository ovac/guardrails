<?php

use Illuminate\Support\Facades\Route;
use \OVAC\Guardrails\Http\Controllers\ActorApprovalsController;

$prefix = trim((string) config('guardrails.route_prefix', 'staff/v1/guardrails'), '/');
$guard = (string) config('guardrails.auth.guard', 'staff');
$defaultMiddleware = ['api','auth:'.$guard];
$middleware = (array) (config('guardrails.middleware') ?? $defaultMiddleware);

$router = app('router');
$aliases = method_exists($router, 'getMiddleware') ? $router->getMiddleware() : [];
$hasAbilities = array_key_exists('abilities', $aliases);
$has2fa = array_key_exists('2fa.enforced', $aliases);

// JSON API routes for Guardrails approval endpoints
Route::prefix($prefix)
    ->middleware($middleware)
    ->as('guardrails.')
    ->group(function () {
        $indexMw = [];
        $viewAbility = (string) config('guardrails.permissions.view','approvals.manage');
        if ($hasAbilities && $viewAbility !== '') {
            $indexMw[] = 'abilities:'.$viewAbility;
        }
        Route::get('', [ActorApprovalsController::class, 'index'])
            ->middleware($indexMw)
            ->name('index');

        $approveMw = [];
        if ($has2fa) $approveMw[] = '2fa.enforced';
        $signAbility = (string) config('guardrails.permissions.sign','approvals.manage');
        if ($hasAbilities && $signAbility !== '') $approveMw[] = 'abilities:'.$signAbility;
        Route::post('{request}/steps/{step}/approve', [ActorApprovalsController::class, 'approveStep'])
            ->middleware($approveMw)
            ->name('steps.approve');
    });
