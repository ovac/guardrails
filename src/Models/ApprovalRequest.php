<?php

namespace OVAC\Guardrails\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * ApprovalRequest is the top-level record for a guarded change.
 *
 * It stores approvable morph target, actor id, state, and payload snapshots.
 */
class ApprovalRequest extends Model
{
    protected $table = 'human_approval_requests';
    protected $guarded = [];

    protected $casts = [
        'new_data' => 'array',
        'original_data' => 'array',
        'context' => 'array',
        'meta' => 'array',
    ];

    /** The target model associated with this request. */
    public function approvable(): MorphTo
    {
        return $this->morphTo();
    }

    /** Steps belonging to the request in ascending level order. */
    public function steps()
    {
        return $this->hasMany(ApprovalStep::class, 'request_id');
    }
}
