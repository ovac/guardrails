<?php

namespace OVAC\Guardrails\Events;

use OVAC\Guardrails\Models\ApprovalSignature;
use OVAC\Guardrails\Models\ApprovalStep;

/**
 * Event fired when an approval signature is recorded for a step.
 */
class ApprovalStepApproved
{
    /**
     * Create a new event instance.
     *
     * @param ApprovalStep $step The step that received the signature
     * @param ApprovalSignature $signature The recorded signature
     */
    public function __construct(
        public ApprovalStep $step,
        public ApprovalSignature $signature
    ) {
    }
}
