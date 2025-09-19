<?php

namespace OVAC\Guardrails\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Facade entry point for building Guardrails approval flows.
 */
class Flow extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'guardrails.flow';
    }
}
