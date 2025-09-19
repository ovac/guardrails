<?php

namespace OVAC\Guardrails\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ApprovalSignature captures a user decision (approve/reject/postponed)
 * on a specific step, with optional comment and metadata.
 *
 * @property int $id
 * @property int $step_id
 * @property int|null $signer_id
 * @property string $decision
 * @property string|null $comment
 * @property array<string, mixed>|null $meta
 * @property \Illuminate\Support\Carbon|null $signed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \OVAC\Guardrails\Models\ApprovalStep|null $step
 */
class ApprovalSignature extends Model
{
    protected $table = 'guardrail_approval_signatures';

    protected $guarded = [];

    protected $casts = [
        'meta' => 'array',
        'signed_at' => 'datetime',
    ];

    /**
     * The step this signature belongs to.
     *
     * @return BelongsTo
     */
    public function step(): BelongsTo
    {
        return $this->belongsTo(ApprovalStep::class, 'step_id');
    }
}
