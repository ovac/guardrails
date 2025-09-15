<?php

namespace OVAC\Guardrails\Support;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Helper to resolve the configured authentication guard and related user.
 */
class Auth
{
    /**
     * Resolve the guard name configured for Guardrails.
     * Falls back to app default or 'web' when unavailable.
     *
     * @return string
     */
    public static function guardName(): string
    {
        $configured = (string) config('guardrails.auth.guard', 'staff');
        $guards = array_keys((array) config('auth.guards', []));
        if (in_array($configured, $guards, true)) {
            return $configured;
        }

        // Fallback to app default guard, then web
        return (string) config('auth.defaults.guard', 'web');
    }

    /**
     * Resolve the guard instance for the configured guard name.
     *
     * @return \Illuminate\Contracts\Auth\Guard|\Illuminate\Contracts\Auth\StatefulGuard
     */
    public static function guard()
    {
        return auth()->guard(self::guardName());
    }

    /**
     * Fetch the currently authenticated user on the configured guard.
     *
     * @return Authenticatable|null
     */
    public static function user(): ?Authenticatable
    {
        return self::guard()->user();
    }

    /**
     * Determine if the configured guard is authenticated.
     *
     * @return bool
     */
    public static function check(): bool
    {
        return self::guard()->check();
    }

    /**
     * Resolve the Eloquent model class configured for the guard provider.
     *
     * @param string|null $guard Guard name or null for the configured one
     * @return string|null Fully-qualified model class or null
     */
    public static function providerModelClass(?string $guard = null): ?string
    {
        $guard = $guard ?: self::guardName();
        $provider = config("auth.guards.$guard.provider");
        if (!$provider) return null;
        return config("auth.providers.$provider.model");
    }

    /**
     * Find a user instance by id using the provider model of the guard.
     *
     * @param mixed $id Primary key
     * @param string|null $guard Guard name or null for the configured one
     * @return Authenticatable|null
     */
    public static function findUserById($id, ?string $guard = null): ?Authenticatable
    {
        $model = self::providerModelClass($guard);
        if ($model && class_exists($model) && method_exists($model, 'find')) {
            return $model::find($id);
        }
        return null;
    }
}
