<?php

namespace OVAC\Guardrails\Events;

use OVAC\Guardrails\Models\ApprovalRequest;

class ApprovalRequestCaptured
{
    public function __construct(public ApprovalRequest $request)
    {
    }
}

