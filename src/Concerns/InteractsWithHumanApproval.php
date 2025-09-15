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

    /** Determine if the authenticated staff may bypass manual approvals. */
    protected function staffCanBypassApprovals(): bool
    {
        $staff = property_exists($this, 'staff') ? $this->staff : auth('staff')->user();
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
