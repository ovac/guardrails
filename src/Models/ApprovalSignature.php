<?php

namespace OVAC\Guardrails\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * ApprovalSignature captures a user decision (approve/reject/postponed)
 * on a specific step, with optional comment and metadata.
 */
class ApprovalSignature extends Model
{
    protected $table = 'human_approval_signatures';
    protected $guarded = [];

    protected $casts = [
        'meta' => 'array',
        'signed_at' => 'datetime',
    ];

    /**
     * The step this signature belongs to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function step()
    {
        return $this->belongsTo(ApprovalStep::class, 'step_id');
    }
}
