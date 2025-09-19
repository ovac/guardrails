<?php

namespace OVAC\Guardrails\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * ApprovalRequest is the top-level record for a guarded change.
 *
 * @property int $id
 * @property string|null $approvable_type
 * @property int|null $approvable_id
 * @property int|null $initiator_id
 * @property string $state
 * @property string|null $description
 * @property array<string, mixed> $new_data
 * @property array<string, mixed>|null $original_data
 * @property array<string, mixed>|null $context
 * @property array<string, mixed>|null $meta
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Model|null $approvable
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \OVAC\Guardrails\Models\ApprovalStep> $steps
 */
class ApprovalRequest extends Model
{
    protected $table = 'guardrail_approval_requests';

    protected $guarded = [];

    protected $casts = [
        'new_data' => 'array',
        'original_data' => 'array',
        'context' => 'array',
        'meta' => 'array',
    ];

    /**
     * Target model associated with this request.
     *
     * @return MorphTo
     */
    public function approvable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the approval steps associated with this request in ascending level order.
     *
     * @return HasMany
     */
    public function steps(): HasMany
    {
        return $this->hasMany(ApprovalStep::class, 'request_id');
    }
}
