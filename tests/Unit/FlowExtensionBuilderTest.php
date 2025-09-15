<?php

use OVAC\Guardrails\Services\Flow;

it('builds a single-step any-of permission flow', function () {
    $flow = Flow::make()->anyOfPermissions(['a','b'])->toStep(2, 'Ops')->build();
    expect($flow)->toHaveCount(1)
        ->and($flow[0]['threshold'])->toBe(2)
        ->and($flow[0]['name'])->toBe('Ops')
        ->and($flow[0]['signers']['permissions_mode'])->toBe('any');
});

it('includes initiator and preapproves by default when enabled', function () {
    $flow = Flow::make()->includeInitiator(true, true)->toStep()->build();
    expect($flow[0]['meta']['include_initiator'])->toBeTrue()
        ->and($flow[0]['meta']['preapprove_initiator'])->toBeTrue();
});
