<?php

namespace OVAC\Guardrails\Events;

use OVAC\Guardrails\Models\ApprovalRequest;

/**
 * Event fired when a request has completed all steps and changes applied.
 */
class ApprovalRequestCompleted
{
    /**
     * Create a new event instance.
     *
     * @param  ApprovalRequest  $request  The approved request.
     */
    public function __construct(public ApprovalRequest $request)
    {
    }
}
