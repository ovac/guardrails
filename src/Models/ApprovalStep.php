<?php

namespace OVAC\Guardrails\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * ApprovalStep defines a level within a multi-step flow.
 *
 * Fields include threshold, status, and signer meta rules.
 */
class ApprovalStep extends Model
{
    protected $table = 'human_approval_steps';
    protected $guarded = [];

    protected $casts = [
        'meta' => 'array',
    ];

    /**
     * Parent request this step belongs to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function request()
    {
        return $this->belongsTo(ApprovalRequest::class, 'request_id');
    }

    /**
     * Signatures recorded against this step.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function signatures()
    {
        return $this->hasMany(ApprovalSignature::class, 'step_id');
    }
}
