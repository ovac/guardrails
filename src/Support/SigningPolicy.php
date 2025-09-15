<?php

namespace OVAC\Guardrails\Support;

use App\Models\Staff;

/**
 * SigningPolicy evaluates whether a staff member is eligible to sign
 * a given step based on guard, permissions, roles, and initiator overlap.
 */
class SigningPolicy
{
    /**
     * Determine if a staff can sign a given step based on step meta signers.
     * Supports any-of/all-of semantics and optional initiator overlap.
     *
     * @param Staff|null $staff
     * @param array $signers
     * @param mixed $context Optional Step or Request context for initiator checks
     */
    /**
     * Check if the given staff satisfies the signer rule.
     *
     * @param Staff|null $staff Current staff user
     * @param array $signers Signer rule (guard, permissions/roles and modes)
     * @param mixed $context Optional Step/Request providing initiator context
     */
    public static function canSign(?Staff $staff, array $signers, $context = null): bool
    {
        if (!$staff) return false;

        $guard = $signers['guard'] ?? 'staff';
        if ($guard && $guard !== 'staff') {
            return false;
        }

        $hasSpatie = method_exists($staff, 'hasPermissionTo') && method_exists($staff, 'hasRole');
        $perms = (array) ($signers['permissions'] ?? []);
        $permMode = in_array(($signers['permissions_mode'] ?? 'all'), ['all','any'], true) ? ($signers['permissions_mode'] ?? 'all') : 'all';
        $roles = (array) ($signers['roles'] ?? []);
        $roleMode = in_array(($signers['roles_mode'] ?? 'all'), ['all','any'], true) ? ($signers['roles_mode'] ?? 'all') : 'all';

        // Permissions check
        if (!empty($perms)) {
            if ($hasSpatie) {
                if ($permMode === 'all') {
                    foreach ($perms as $p) if (!$staff->hasPermissionTo($p)) return false;
                } else {
                    $ok = false;
                    foreach ($perms as $p) { if ($staff->hasPermissionTo($p)) { $ok = true; break; } }
                    if (!$ok) return false;
                }
            } else {
                $abilities = (array) optional($staff->currentAccessToken())->abilities;
                if ($permMode === 'all') {
                    foreach ($perms as $p) if (!in_array($p, $abilities, true)) return false;
                } else {
                    $ok = false;
                    foreach ($perms as $p) { if (in_array($p, $abilities, true)) { $ok = true; break; } }
                    if (!$ok) return false;
                }
            }
        }

        // Roles check
        if (!empty($roles)) {
            if ($hasSpatie) {
                if ($roleMode === 'all') {
                    foreach ($roles as $r) if (!$staff->hasRole($r)) return false;
                } else {
                    $ok = false;
                    foreach ($roles as $r) { if ($staff->hasRole($r)) { $ok = true; break; } }
                    if (!$ok) return false;
                }
            } else {
                // Roles unsupported without Spatie; explicit roles requirement fails
                return false;
            }
        }

        // Initiator overlap constraints (Spatie only)
        $requireSamePerm = (bool) ($signers['same_permission_as_initiator'] ?? false);
        $requireSameRole = (bool) ($signers['same_role_as_initiator'] ?? false);
        if (($requireSamePerm || $requireSameRole) && $hasSpatie) {
            $initiator = null;
            if ($context && method_exists($context, 'request')) {
                $initiatorId = optional($context->request)->actor_staff_id;
                if ($initiatorId) $initiator = Staff::find($initiatorId);
            } elseif ($context && property_exists($context, 'actor_staff_id')) {
                $initiator = Staff::find($context->actor_staff_id);
            }

            if ($initiator) {
                if ($requireSamePerm && !empty($perms)) {
                    $staffPerms = collect($staff->getAllPermissions())->pluck('name')->all();
                    $initPerms = collect($initiator->getAllPermissions())->pluck('name')->all();
                    $intersection = array_values(array_intersect($perms, $staffPerms, $initPerms));
                    if (empty($intersection)) return false;
                }
                if ($requireSameRole && !empty($roles)) {
                    $staffRoles = method_exists($staff, 'roles') ? $staff->roles->pluck('name')->all() : [];
                    $initRoles = method_exists($initiator, 'roles') ? $initiator->roles->pluck('name')->all() : [];
                    $intersection = array_values(array_intersect($roles, $staffRoles, $initRoles));
                    if (empty($intersection)) return false;
                }
            }
        }

        return true;
    }
}
