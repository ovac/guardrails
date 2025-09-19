<?php

namespace OVAC\Guardrails\Services;

use Illuminate\Support\Arr;
use OVAC\Guardrails\Contracts\FlowExtender;

/**
 * Intercepts controller-level mutations and creates approval requests
 * when a change involves guarded attributes and an authenticated user.
 */
class ControllerInterceptor
{
    /**
     * Intercept the given mutation payload for the provided model.
     *
     * Options:
     * - event: creating|updating|custom (default updating)
     * - only: array of attribute keys to guard (overrides model rule)
     * - except: array of attribute keys to ignore
     * - flow: preset flow array (overrides model flow)
     * - extender: FlowExtender instance to build a flow
     * - description: summary attached to the request
     * - meta: array of extra data stored on the request record
     *
     * @param  object  $model    Eloquent-like model being mutated.
     * @param  array<string, mixed>  $changes  Proposed attribute changes.
     * @param  array<string, mixed>  $options  Interceptor options (event, only, except, flow, extender).
     * @return array{captured: bool, request_id: int|null, changes: array<string, mixed>}
     */
    public static function intercept($model, array $changes, array $options = []): array
    {
        if (!\OVAC\Guardrails\Support\Auth::check()) {
            return ['captured' => false, 'request_id' => null, 'changes' => $changes];
        }

        $event = (string) ($options['event'] ?? 'updating');

        // Normalize keys to watch
        $only = array_values(array_unique((array) ($options['only'] ?? [])));
        $except = array_values(array_unique((array) ($options['except'] ?? [])));

        // Determine model-declared attributes to guard
        $modelAttrs = [];
        if (method_exists($model, 'guardrailAttributes')) {
            $modelAttrs = (array) $model->guardrailAttributes();
        } elseif (property_exists($model, 'guardrailAttributes') && is_array($model->guardrailAttributes)) {
            $modelAttrs = (array) $model->guardrailAttributes;
        }

        // Final attributes to evaluate
        if ($only) {
            $watch = $only;
        } elseif ($modelAttrs) {
            $watch = $modelAttrs;
        } else {
            $watch = array_keys($changes);
        }
        if ($except) {
            $watch = array_values(array_diff($watch, $except));
        }

        $guardable = Arr::only($changes, $watch);

        // Custom per-model logic hook
        $requires = false;
        if (method_exists($model, 'requiresGuardrailApproval')) {
            $requires = (bool) $model->requiresGuardrailApproval($guardable, $event);
        }

        $shouldGuard = (!empty($guardable) && !method_exists($model, 'requiresGuardrailApproval')) || ($requires === true);
        if (!$shouldGuard) {
            return ['captured' => false, 'request_id' => null, 'changes' => $changes];
        }

        // Capture request (optionally with custom flow or extender)
        if (!empty($options['extender'])) {
            $ext = $options['extender'];
            if ($ext instanceof FlowExtender) {
                $options['flow'] = $ext->build();
            }
            unset($options['extender']);
        }

        $captureOptions = [];
        foreach (['description', 'meta', 'flow', 'context', 'request'] as $key) {
            if (array_key_exists($key, $options)) {
                $captureOptions[$key] = $options[$key];
            }
        }

        $req = \OVAC\Guardrails\Services\GuardrailApprovalService::capture($model, $guardable, $event, $captureOptions);
        return ['captured' => true, 'request_id' => $req->id, 'changes' => $guardable];
    }
}
