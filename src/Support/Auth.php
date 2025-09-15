<?php

namespace OVAC\Guardrails\Support;

use Illuminate\Contracts\Auth\Authenticatable;

class Auth
{
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

    public static function guard()
    {
        return auth()->guard(self::guardName());
    }

    public static function user(): ?Authenticatable
    {
        return self::guard()->user();
    }

    public static function check(): bool
    {
        return self::guard()->check();
    }

    public static function providerModelClass(?string $guard = null): ?string
    {
        $guard = $guard ?: self::guardName();
        $provider = config("auth.guards.$guard.provider");
        if (!$provider) return null;
        return config("auth.providers.$provider.model");
    }

    public static function findUserById($id, ?string $guard = null): ?Authenticatable
    {
        $model = self::providerModelClass($guard);
        if ($model && class_exists($model) && method_exists($model, 'find')) {
            return $model::find($id);
        }
        return null;
    }
}
