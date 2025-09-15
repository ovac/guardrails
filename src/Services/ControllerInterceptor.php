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
     *
     * @param object $model Eloquent-like model being mutated
     * @param array $changes Proposed attribute changes
     * @param array $options Interceptor options (event, only, except, flow, extender)
     * @return array{captured:bool,request_id:int|null,changes:array}
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
        if (method_exists($model, 'humanGuardAttributes')) {
            $modelAttrs = (array) $model->humanGuardAttributes();
        } elseif (property_exists($model, 'humanGuardAttributes') && is_array($model->humanGuardAttributes)) {
            $modelAttrs = (array) $model->humanGuardAttributes;
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
        if (method_exists($model, 'requiresHumanApproval')) {
            $requires = (bool) $model->requiresHumanApproval($guardable, $event);
        }

        $shouldGuard = (!empty($guardable) && !method_exists($model, 'requiresHumanApproval')) || ($requires === true);
        if (!$shouldGuard) {
            return ['captured' => false, 'request_id' => null, 'changes' => $changes];
        }

        // Capture request (optionally with custom flow or extender)
        if (!empty($options['extender'])) {
            $ext = $options['extender'];
            if ($ext instanceof FlowExtender) {
                $options['flow'] = $ext->build();
            }
        }
        if (!empty($options['flow']) && method_exists(HumanApprovalService::class, 'capture')) {
            $model->humanApprovalFlow = fn () => (array) $options['flow'];
        }

        $req = HumanApprovalService::capture($model, $guardable, $event);
        return ['captured' => true, 'request_id' => $req->id, 'changes' => $guardable];
    }
}
