<?php

namespace OVAC\Guardrails\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Simple user fixture with array-based permissions and roles for testing.
 */
class User extends Authenticatable
{
    protected $table = 'users';

    protected $guarded = [];

    protected $casts = [
        'perms' => 'array',
        'roles' => 'array',
    ];

    /**
     * Determine if the user owns the provided permission string.
     *
     * @param  string  $perm
     * @return bool
     */
    public function hasPermissionTo($perm): bool
    {
        $perms = (array) ($this->perms ?? []);
        return in_array($perm, $perms, true);
    }

    /**
     * Determine if the user owns the provided role string.
     *
     * @param  string  $role
     * @return bool
     */
    public function hasRole($role): bool
    {
        $roles = (array) ($this->roles ?? []);
        return in_array($role, $roles, true);
    }
}
