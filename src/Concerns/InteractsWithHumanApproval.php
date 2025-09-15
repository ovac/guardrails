<?php

namespace OVAC\Guardrails\Concerns;

use OVAC\Guardrails\Services\ControllerInterceptor;

/**
 * Trait: InteractsWithHumanApproval
 *
 * Controller helper to intercept mutations and route them through
 * Guardrails when enabled via configuration.
 */
trait InteractsWithHumanApproval
{
    /**
     * Controller-level helper to intercept a mutation for human approval.
     * No-op when disabled via config('guardrails.controller.enabled') or legacy key.
     *
     * @param object $model Eloquent-like model instance being mutated
     * @param array $changes The proposed attribute changes (key => value)
     * @param array $options Interceptor options (event, only, except, flow, extender)
     * @return array{captured:bool,request_id:int|null,changes:array}
     */
    protected function humanApprovalIntercept($model, array $changes, array $options = []): array
    {
        $enabled = config('guardrails.controller.enabled');
        if ($enabled === null) {
            $enabled = config('human-approval.controller.enabled', true);
        }
        if ($enabled === false) {
            return ['captured' => false, 'request_id' => null, 'changes' => $changes];
        }
        return ControllerInterceptor::intercept($model, $changes, $options);
    }

    /**
     * Determine if the authenticated user may bypass manual approvals.
     *
     * Implementors may override this to plug in bespoke bypass rules.
     *
     * @return bool
     */
    protected function staffCanBypassApprovals(): bool
    {
        $staff = property_exists($this, 'staff') ? $this->staff : (property_exists($this, 'user') ? $this->user : \OVAC\Guardrails\Support\Auth::user());
        if (!$staff) {
            return false;
        }
        try {
            return ($staff->hasPermissionTo('bypass.manual.approvals') || optional($staff->currentAccessToken())->can('bypass.manual.approvals'));
        } catch (\Throwable $e) {
            return false;
        }
    }
}
