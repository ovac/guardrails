<?php

namespace OVAC\Guardrails\Events;

use OVAC\Guardrails\Models\ApprovalSignature;
use OVAC\Guardrails\Models\ApprovalStep;

class ApprovalStepApproved
{
    public function __construct(
        public ApprovalStep $step,
        public ApprovalSignature $signature
    ) {
    }
}

