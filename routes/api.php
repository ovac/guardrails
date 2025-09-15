<?php

use Illuminate\Support\Facades\Route;
use \OVAC\Guardrails\Http\Controllers\HumanApprovalsController;

$prefix = trim((string) config('guardrails.route_prefix', 'staff/v1/guardrails'), '/');
$middleware = (array) config('guardrails.middleware', ['api','auth:staff']);

// JSON API routes for Guardrails approval endpoints
Route::prefix($prefix)
    ->middleware($middleware)
    ->as('guardrails.')
    ->group(function () {
        Route::get('', [HumanApprovalsController::class, 'index'])
            ->middleware('abilities:'.config('guardrails.permissions.view','approvals.manage'))
            ->name('index');

        Route::post('{request}/steps/{step}/approve', [HumanApprovalsController::class, 'approveStep'])
            ->middleware(['2fa.enforced','abilities:'.config('guardrails.permissions.sign','approvals.manage')])
            ->name('steps.approve');
    });
