<?php

namespace OVAC\Guardrails\Events;

use OVAC\Guardrails\Models\ApprovalSignature;
use OVAC\Guardrails\Models\ApprovalStep;

/**
 * Event fired when a rejection signature is recorded for an approval step.
 */
class ApprovalStepRejected
{
    /**
     * Create a new event instance.
     *
     * @param  ApprovalStep  $step        Step that was rejected.
     * @param  ApprovalSignature  $signature  Signature documenting the rejection.
     */
    public function __construct(
        public ApprovalStep $step,
        public ApprovalSignature $signature
    ) {
    }
}

