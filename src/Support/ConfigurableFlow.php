<?php

namespace OVAC\Guardrails\Support;

/**
 * Resolve Guardrails approval flows from config with sane fallbacks.
 *
 * Intended for controller interceptors: pull an override from
 * guardrails.flows.<feature>.<action> and merge runtime meta
 * (e.g., summary, hint) onto each step.
 */
class ConfigurableFlow
{
    /**
     * Resolve a configured flow or return the provided fallback.
     *
     * @param  string  $key           Dot-notated key: feature.action (e.g., orders.approve)
     * @param  array<int, array>|null $fallback      Flow to use when config is missing/empty
     * @param  array<string, mixed>   $metaDefaults  Meta merged onto each step when absent
     * @return array<int, array>|null
     */
    public static function resolve(string $key, ?array $fallback = null, array $metaDefaults = []): ?array
    {
        $flow = static::getConfigFlow($key);

        if (!is_array($flow) || $flow === []) {
            $flow = $fallback;
        }

        if (!is_array($flow)) {
            return $flow;
        }

        $flow = static::normalizeStepsArray($flow);

        return static::mergeMetaDefaults($flow, $metaDefaults);
    }

    /**
     * Determine whether a configured flow exists for the given key.
     */
    public static function has(string $key): bool
    {
        $flow = static::getConfigFlow($key);
        return is_array($flow) && $flow !== [];
    }

    /**
     * Retrieve a flow from configuration using dot notation.
     *
     * @return mixed
     */
    protected static function getConfigFlow(string $key)
    {
        $flows = config('guardrails.flows');

        if (!is_array($flows) || $flows === []) {
            return null;
        }

        // Allow flat dot-notation keys (e.g., 'orders.approve' => [...])
        if (array_key_exists($key, $flows)) {
            return $flows[$key];
        }

        $flow = $flows;

        foreach (explode('.', $key) as $segment) {
            if (!is_array($flow) || !array_key_exists($segment, $flow)) {
                return null;
            }
            $flow = $flow[$segment];
        }

        return $flow;
    }

    /**
     * Accept both list-of-steps and single-step arrays by normalizing to a list.
     *
     * @param  array<int|string, mixed>  $flow
     * @return array<int, array>
     */
    protected static function normalizeStepsArray(array $flow): array
    {
        if (!static::isList($flow) && (array_key_exists('name', $flow) || array_key_exists('signers', $flow) || array_key_exists('threshold', $flow))) {
            // Single step provided as associative array; wrap it
            return [$flow];
        }

        return $flow;
    }

    /**
     * Merge default meta values onto each configured step.
     *
     * @param  array<int, array>       $flow
     * @param  array<string, mixed>    $metaDefaults
     * @return array<int, array>
     */
    protected static function mergeMetaDefaults(array $flow, array $metaDefaults): array
    {
        if ($metaDefaults === []) {
            return $flow;
        }

        return array_map(function ($step) use ($metaDefaults) {
            $meta = is_array($step['meta'] ?? null) ? $step['meta'] : [];

            foreach ($metaDefaults as $key => $value) {
                if (!array_key_exists($key, $meta)) {
                    $meta[$key] = $value;
                }
            }

            $step['meta'] = $meta;

            return $step;
        }, $flow);
    }

    /**
     * Polyfill for array_is_list to support older runtimes.
     */
    protected static function isList(array $array): bool
    {
        if (function_exists('array_is_list')) {
            return array_is_list($array);
        }

        $i = 0;
        foreach ($array as $key => $_) {
            if ($key !== $i) {
                return false;
            }
            $i++;
        }

        return true;
    }
}
