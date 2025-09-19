<?php

namespace OVAC\Guardrails\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ApprovalStep defines a level within a multi-step flow.
 *
 * @property int $id
 * @property int $request_id
 * @property int $level
 * @property string $name
 * @property int $threshold
 * @property string $status
 * @property array<string, mixed>|null $meta
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \OVAC\Guardrails\Models\ApprovalRequest|null $request
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \OVAC\Guardrails\Models\ApprovalSignature> $signatures
 */
class ApprovalStep extends Model
{
    protected $table = 'guardrail_approval_steps';

    protected $guarded = [];

    protected $casts = [
        'meta' => 'array',
    ];

    protected $appends = [
        'permissions_required',
        'permissions_mode',
        'roles_required',
        'roles_mode',
    ];

    /**
     * Parent request this step belongs to.
     *
     * @return BelongsTo
     */
    public function request(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class, 'request_id');
    }

    /**
     * Signatures recorded against this step.
     *
     * @return HasMany
     */
    public function signatures(): HasMany
    {
        return $this->hasMany(ApprovalSignature::class, 'step_id');
    }

    /**
     * Determine the signer configuration defined for this step.
     *
     * @return array<string, mixed>
     */
    protected function signerMeta(): array
    {
        $meta = (array) ($this->meta ?? []);

        return (array) ($meta['signers'] ?? []);
    }

    /**
     * Permissions required for a user to sign this step.
     *
     * @return array<int, string>
     */
    public function getPermissionsRequiredAttribute(): array
    {
        $signers = $this->signerMeta();
        $permissions = isset($signers['permissions']) ? (array) $signers['permissions'] : [];

        $normalized = [];
        foreach ($permissions as $permission) {
            $permission = trim((string) $permission);
            if ($permission !== '') {
                $normalized[] = $permission;
            }
        }

        return $normalized;
    }

    /**
     * Permission aggregation mode (any-of vs all-of).
     *
     * @return string
     */
    public function getPermissionsModeAttribute(): string
    {
        $signers = $this->signerMeta();
        $mode = isset($signers['permissions_mode']) ? (string) $signers['permissions_mode'] : 'all';

        return in_array($mode, ['all', 'any'], true) ? $mode : 'all';
    }

    /**
     * Roles required for a user to sign this step.
     *
     * @return array<int, string>
     */
    public function getRolesRequiredAttribute(): array
    {
        $signers = $this->signerMeta();
        $roles = isset($signers['roles']) ? (array) $signers['roles'] : [];

        $normalized = [];
        foreach ($roles as $role) {
            $role = trim((string) $role);
            if ($role !== '') {
                $normalized[] = $role;
            }
        }

        return $normalized;
    }

    /**
     * Role aggregation mode (any-of vs all-of).
     *
     * @return string
     */
    public function getRolesModeAttribute(): string
    {
        $signers = $this->signerMeta();
        $mode = isset($signers['roles_mode']) ? (string) $signers['roles_mode'] : 'all';

        return in_array($mode, ['all', 'any'], true) ? $mode : 'all';
    }
}
