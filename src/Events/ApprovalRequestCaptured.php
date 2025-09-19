<?php

namespace OVAC\Guardrails\Events;

use OVAC\Guardrails\Models\ApprovalRequest;

/**
 * Event fired when a new approval request has been captured.
 *
 * Use to notify teams, trigger external signing, or log audits.
 */
class ApprovalRequestCaptured
{
    /**
     * Create a new event instance.
     *
     * @param  ApprovalRequest  $request  The captured request.
     */
    public function __construct(public ApprovalRequest $request)
    {
    }
}
