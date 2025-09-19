<?php

namespace OVAC\Guardrails\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use OVAC\Guardrails\Models\ApprovalRequest;
use OVAC\Guardrails\Models\ApprovalStep;

/**
 * SigningPolicy evaluates whether a user is eligible to sign
 * a given step based on permissions, roles, and initiator overlap.
 */
class SigningPolicy
{
    /**
     * Determine if a user can sign a given step based on step meta signers.
     * Supports any-of/all-of semantics and optional initiator overlap.
     *
     * @param  Authenticatable|null  $user     Current authenticated user.
     * @param  array<string, mixed>  $signers  Signer rule configuration (permissions/roles/modes).
     * @param  \OVAC\Guardrails\Models\ApprovalStep|\OVAC\Guardrails\Models\ApprovalRequest|null  $context  Optional step/request context for initiator checks.
     * @return bool
     */
    public static function canSign(?Authenticatable $user, array $signers, $context = null): bool
    {
        if (!$user) return false;

        $hasSpatie = method_exists($user, 'hasPermissionTo') && method_exists($user, 'hasRole');
        $perms = (array) ($signers['permissions'] ?? []);
        $permMode = in_array(($signers['permissions_mode'] ?? 'all'), ['all', 'any'], true) ? ($signers['permissions_mode'] ?? 'all') : 'all';
        $roles = (array) ($signers['roles'] ?? []);
        $roleMode = in_array(($signers['roles_mode'] ?? 'all'), ['all', 'any'], true) ? ($signers['roles_mode'] ?? 'all') : 'all';

        // Permissions check
        if (!empty($perms)) {
            if ($hasSpatie) {
                if ($permMode === 'all') {
                    foreach ($perms as $p) if (!$user->hasPermissionTo($p)) return false;
                } else {
                    $ok = false;
                    foreach ($perms as $p) {
                        if ($user->hasPermissionTo($p)) {
                            $ok = true; break;
                        }
                    }
                    if (!$ok) return false;
                }
            } else {
                $abilities = (array) optional($user->currentAccessToken())->abilities;
                if ($permMode === 'all') {
                    foreach ($perms as $p) if (!in_array($p, $abilities, true)) return false;
                } else {
                    $ok = false;
                    foreach ($perms as $p) {
                        if (in_array($p, $abilities, true)) {
                            $ok = true; break;
                        }
                    }
                    if (!$ok) return false;
                }
            }
        }

        // Roles check
        if (!empty($roles)) {
            $userRoles = $hasSpatie ? [] : self::resolveRoles($user);
            if ($hasSpatie) {
                $userRoles = self::resolveSpatieRoles($user);
                if ($roleMode === 'all') {
                    foreach ($roles as $r) if (!$user->hasRole($r)) return false;
                } else {
                    $ok = false;
                    foreach ($roles as $r) {
                        if ($user->hasRole($r)) {
                            $ok = true; break;
                        }
                    }
                    if (!$ok) return false;
                }
            } else {
                if (empty($userRoles)) {
                    return false;
                }
                if ($roleMode === 'all') {
                    foreach ($roles as $r) if (!in_array($r, $userRoles, true)) return false;
                } else {
                    $ok = false;
                    foreach ($roles as $r) {
                        if (in_array($r, $userRoles, true)) {
                            $ok = true; break;
                        }
                    }
                    if (!$ok) return false;
                }
            }
        }

        // Initiator overlap constraints (Spatie only)
        $requireSamePerm = (bool) ($signers['same_permission_as_initiator'] ?? false);
        $requireSameRole = (bool) ($signers['same_role_as_initiator'] ?? false);
        if (($requireSamePerm || $requireSameRole) && $hasSpatie) {
            $initiator = null;
            if ($context instanceof ApprovalStep) {
                $relatedRequest = $context->request()->first();
                $initiatorId = $relatedRequest?->initiator_id;
                if ($initiatorId) {
                    $initiator = Auth::findUserById($initiatorId);
                }
            } elseif ($context instanceof ApprovalRequest) {
                $initiator = Auth::findUserById($context->initiator_id);
            }

            if ($initiator) {
                if ($requireSamePerm && !empty($perms)) {
                    $userPerms = collect($user->getAllPermissions())->pluck('name')->all();
                    $initPerms = collect($initiator->getAllPermissions())->pluck('name')->all();
                    $intersection = array_values(array_intersect($perms, $userPerms, $initPerms));
                    if (empty($intersection)) return false;
                }
                if ($requireSameRole && !empty($roles)) {
                    $userRoles = self::resolveSpatieRoles($user);
                    $initRoles = self::resolveSpatieRoles($initiator);
                    $intersection = array_values(array_intersect($roles, $userRoles, $initRoles));
                    if (empty($intersection)) return false;
                }
            }
        }

        return true;
    }

    /**
     * Resolve role names for the given user using configured callbacks.
     *
     * @param  Authenticatable|null  $user
     * @return array<int, string>
     */
    protected static function resolveRoles(?Authenticatable $user): array
    {
        if (!$user) {
            return [];
        }

        $resolver = config('guardrails.signing.resolve_roles_using');
        if ($resolver instanceof \Closure) {
            $roles = $resolver($user);
            return array_values(array_unique((array) $roles));
        }

        if (property_exists($user, 'roles') && is_array($user->roles)) {
            return array_values($user->roles);
        }

        return [];
    }

    /**
     * Extract role names when Spatie permissions is installed.
     *
     * @param  Authenticatable  $user
     * @return array<int, string>
     */
    protected static function resolveSpatieRoles(Authenticatable $user): array
    {
        if (!method_exists($user, 'roles')) {
            return [];
        }

        $relation = $user->roles();
        if (method_exists($relation, 'pluck')) {
            return $relation->pluck('name')->all();
        }

        return [];
    }
}
