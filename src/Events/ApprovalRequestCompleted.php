<?php

namespace OVAC\Guardrails\Events;

use OVAC\Guardrails\Models\ApprovalRequest;

class ApprovalRequestCompleted
{
    public function __construct(public ApprovalRequest $request)
    {
    }
}

