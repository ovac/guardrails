<?php

namespace OVAC\Guardrails\Concerns;

use OVAC\Guardrails\Services\ActorApprovalService;

trait ActorGuarded
{
    public static function bootActorGuarded(): void
    {
        static::updating(function ($model) {
            $dirty = $model->getDirty();
            if (!\OVAC\Guardrails\Support\Auth::check()) {
                return true;
            }
            ActorApprovalService::capture($model, $dirty, 'updating');
            return false;
        });
    }
}

