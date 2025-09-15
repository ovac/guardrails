<?php

namespace OVAC\Guardrails\Concerns;

use OVAC\Guardrails\Services\ControllerInterceptor;

trait InteractsWithActorApproval
{
    protected function actorApprovalIntercept($model, array $changes, array $options = []): array
    {
        $enabled = config('guardrails.controller.enabled', true);
        if ($enabled === false) {
            return ['captured' => false, 'request_id' => null, 'changes' => $changes];
        }
        return ControllerInterceptor::intercept($model, $changes, $options);
    }
}

