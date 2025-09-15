<?php

namespace OVAC\Guardrails\Facades;

use Illuminate\Support\Facades\Facade;

class Flow extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'guardrails.flow';
    }
}

