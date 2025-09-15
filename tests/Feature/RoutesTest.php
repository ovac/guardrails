<?php

use Illuminate\Support\Facades\Route;

it('registers routes with names', function () {
    expect(Route::has('guardrails.index'))->toBeTrue();
});

