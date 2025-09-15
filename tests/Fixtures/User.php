<?php

namespace OVAC\Guardrails\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    protected $table = 'users';
    protected $guarded = [];
    protected $casts = [
        'perms' => 'array',
        'roles' => 'array',
    ];

    public function hasPermissionTo($perm): bool
    {
        $perms = (array) ($this->perms ?? []);
        return in_array($perm, $perms, true);
    }

    public function hasRole($role): bool
    {
        $roles = (array) ($this->roles ?? []);
        return in_array($role, $roles, true);
    }
}

