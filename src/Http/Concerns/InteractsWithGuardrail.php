<?php

namespace OVAC\Guardrails\Http\Concerns;

use OVAC\Guardrails\Services\ControllerInterceptor;
use OVAC\Guardrails\Support\ConfigurableFlow;

/**
 * Controller helper to funnel mutations through Guardrails approvals.
 */
trait InteractsWithGuardrail
{
    /**
     * Intercept controller changes and stage them for approval when configured.
     *
     * @param  object  $model    Target model instance being mutated.
     * @param  array<string, mixed>  $changes  Attributes proposed by the request.
     * @param  array<string, mixed>  $options  Additional interceptor configuration.
     * @return array{captured: bool, request_id: ?int, changes: array<string, mixed>}
     */
    protected function guardrailIntercept($model, array $changes, array $options = []): array
    {
        $enabled = config('guardrails.controller.enabled', true);
        if ($enabled === false) {
            return ['captured' => false, 'request_id' => null, 'changes' => $changes];
        }
        if (!array_key_exists('request', $options) && function_exists('request')) {
            $options['request'] = request();
        }
        return ControllerInterceptor::intercept($model, $changes, $options);
    }

    /**
     * Resolve a configured flow (guardrails.flows.<feature>.<action>) with meta defaults.
     *
     * @param  string                   $key           Dot key: feature.action (e.g., orders.approve)
     * @param  array<int, array>|null   $fallback      Flow when config is missing/empty
     * @param  array<string, mixed>     $metaDefaults  Meta merged onto each step if absent
     */
    protected function guardrailFlow(string $key, ?array $fallback = null, array $metaDefaults = []): ?array
    {
        return ConfigurableFlow::resolve($key, $fallback, $metaDefaults);
    }
}
