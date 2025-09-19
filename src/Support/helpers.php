<?php

if (!function_exists('flow')) {
    /**
     * Create a new flow builder instance.
     *
     * @return \OVAC\Guardrails\Services\Flow
     */
    function flow(): \OVAC\Guardrails\Services\Flow
    {
        return app('guardrails.flow');
    }
}
