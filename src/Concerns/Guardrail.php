<?php

namespace OVAC\Guardrails\Concerns;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;
use OVAC\Guardrails\Services\GuardrailApprovalService;
use OVAC\Guardrails\Support\ApprovalContext;

/**
 * Trait to stage guarded model updates for Guardrails approvals.
 */
trait Guardrail
{
    protected ?ApprovalContext $guardrailsContext = null;

    protected bool $guardrailsBypass = false;

    /**
     * Register Guardrails lifecycle hooks on the consuming model.
     *
     * @return void
     */
    public static function bootGuardrail(): void
    {
        static::updating(function ($model) {
            if (!\OVAC\Guardrails\Support\Auth::check()) {
                return true;
            }

            if ($model->guardrailsBypass ?? false) {
                $model->guardrailsContext?->clear();
                $model->guardrailsBypass = false;
                return true;
            }

            $dirty = $model->getDirty();
            if (empty($dirty)) {
                $model->guardrailsContext?->clear();
                return true;
            }

            $options = $model->guardrailsContext?->options() ?? [];
            $event = (string) ($options['event'] ?? 'updating');
            unset($options['event']);

            [$guardable, $options] = self::resolveGuardableAttributes($model, $dirty, $options);
            if (empty($guardable)) {
                $model->guardrailsContext?->clear();
                return true;
            }

            if (method_exists($model, 'requiresGuardrailApproval')) {
                $requires = (bool) $model->requiresGuardrailApproval($guardable, $event);
                if ($requires !== true) {
                    $model->guardrailsContext?->clear();
                    return true;
                }
            }

            GuardrailApprovalService::capture($model, $guardable, $event, $options);
            $model->guardrailsContext?->clear();
            return false;
        });

        static::saved(fn ($model) => $model->guardrailsContext?->clear());
        static::deleted(fn ($model) => $model->guardrailsContext?->clear());

        if (in_array(SoftDeletes::class, class_uses_recursive(static::class), true)) {
            static::restored(fn ($model) => $model->guardrailsContext?->clear());
        }
    }

    /**
     * Access the Guardrails approval context for the current model instance.
     *
     * @return ApprovalContext
     */
    public function guardrails(): ApprovalContext
    {
        if (!$this->guardrailsContext instanceof ApprovalContext) {
            $this->guardrailsContext = new ApprovalContext($this);
        }

        return $this->guardrailsContext;
    }

    /**
     * Temporarily bypass Guardrails interception for the current model.
     *
     * @template TReturn
     * @param  callable(self):TReturn|null  $callback
     * @return ($callback is null ? static : TReturn)
     */
    public function withoutGuardrail(?callable $callback = null)
    {
        if ($callback === null) {
            $this->guardrailsBypass = true;
            return $this;
        }

        $previous = $this->guardrailsBypass;
        $this->guardrailsBypass = true;

        try {
            return $callback($this);
        } finally {
            $this->guardrailsBypass = $previous;
        }
    }

    /**
     * Determine which dirty attributes should be staged for approval.
     *
     * @param  object  $model    Consuming model instance being inspected.
     * @param  array<string, mixed>  $dirty    Attribute changes detected on the model.
     * @param  array<string, mixed>  $options  Additional guardrails options gathered from context.
     * @return array{array<string, mixed>, array<string, mixed>} Tuple of guardable attributes and remaining options.
     */
    protected static function resolveGuardableAttributes(object $model, array $dirty, array $options): array
    {
        $only = array_values(array_unique((array) ($options['only'] ?? [])));
        $except = array_values(array_unique((array) ($options['except'] ?? [])));
        unset($options['only'], $options['except']);

        $modelAttrs = [];
        if (method_exists($model, 'guardrailAttributes')) {
            $modelAttrs = (array) $model->guardrailAttributes();
        } elseif (property_exists($model, 'guardrailAttributes') && is_array($model->guardrailAttributes)) {
            $modelAttrs = (array) $model->guardrailAttributes;
        }

        if (!empty($only)) {
            $watch = $only;
        } elseif (!empty($modelAttrs)) {
            $watch = $modelAttrs;
        } else {
            $watch = array_keys($dirty);
        }

        if (!empty($except)) {
            $watch = array_values(array_diff($watch, $except));
        }

        $guardable = Arr::only($dirty, $watch);

        return [$guardable, $options];
    }
}
