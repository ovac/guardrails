<?php

if (!function_exists('flow')) {
    function flow(): \OVAC\Guardrails\Services\Flow
    {
        return new \OVAC\Guardrails\Services\Flow();
    }
}

