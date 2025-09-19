<?php

namespace OVAC\Guardrails\Events;

use OVAC\Guardrails\Models\ApprovalRequest;
use OVAC\Guardrails\Models\ApprovalSignature;
use OVAC\Guardrails\Models\ApprovalStep;

/**
 * Event fired when an approval request is rejected at any step.
 */
class ApprovalRequestRejected
{
    /**
     * Create a new event instance.
     *
     * @param  ApprovalRequest  $request   Request that was rejected in its workflow.
     * @param  ApprovalStep     $step      Step where the rejection occurred.
     * @param  ApprovalSignature  $signature  Signature capturing the rejection decision.
     */
    public function __construct(
        public ApprovalRequest $request,
        public ApprovalStep $step,
        public ApprovalSignature $signature
    ) {
    }
}
