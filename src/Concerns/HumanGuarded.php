<?php

namespace OVAC\Guardrails\Concerns;

use OVAC\Guardrails\Models\ApprovalRequest;
use OVAC\Guardrails\Services\HumanApprovalService;

/**
 * Trait: HumanGuarded
 *
 * Attach to Eloquent models to stage guarded mutations as
 * human approval requests when performed by authenticated staff.
 *
 * Implementation notes:
 * - Define either `humanGuardAttributes(): array` or `$humanGuardAttributes` on the model
 *   to list attributes that require approval.
 */
trait HumanGuarded
{
    /**
     * Eloquent boot hook to intercept updates and capture an approval request.
     */
    public static function bootHumanGuarded(): void
    {
        static::updating(function ($model) {
            $dirty = $model->getDirty();

            // Skip if no authenticated user on the configured guard
            if (!\OVAC\Guardrails\Support\Auth::check()) {
                return true;
            }

            // Capture and prevent write; controller/service applies changes on approval
            $req = HumanApprovalService::capture($model, $dirty, 'updating');
            return false;
        });
    }
}
