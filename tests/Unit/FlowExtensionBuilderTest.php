<?php

/**
 * Unit tests covering the fluent Flow builder helpers.
 */
use OVAC\Guardrails\Services\Flow;

it('builds a single-step any-of permission flow', function () {
    $flow = Flow::make()->anyOfPermissions(['a', 'b'])->signedBy(2, 'Ops')->build();
    expect($flow)->toHaveCount(1)
        ->and($flow[0]['threshold'])->toBe(2)
        ->and($flow[0]['name'])->toBe('Ops')
        ->and($flow[0]['signers']['permissions_mode'])->toBe('any');
});

it('includes initiator and preapproves by default when enabled', function () {
    $flow = Flow::make()->includeInitiator(true, true)->signedBy()->build();
    expect($flow[0]['meta']['include_initiator'])->toBeTrue()
        ->and($flow[0]['meta']['preapprove_initiator'])->toBeTrue();
});

it('does not leak permissions or roles between steps', function () {
    $flow = Flow::make()
        ->anyOfPermissions(['config.manage'])
        ->anyOfRoles(['configurator'])
        ->signedBy(1, 'Config Review')
        ->anyOfPermissions(['ops.manage'])
        ->anyOfRoles(['ops_manager'])
        ->signedBy(1, 'Operations Manager')
        ->build();

    expect($flow)->toHaveCount(2);

    expect($flow[0]['signers']['permissions'])->toBe(['config.manage'])
        ->and($flow[0]['signers']['permissions_mode'])->toBe('any')
        ->and($flow[0]['signers']['roles'])->toBe(['configurator'])
        ->and($flow[0]['signers']['roles_mode'])->toBe('any');

    expect($flow[1]['signers']['permissions'])->toBe(['ops.manage'])
        ->and($flow[1]['signers']['permissions_mode'])->toBe('any')
        ->and($flow[1]['signers']['roles'])->toBe(['ops_manager'])
        ->and($flow[1]['signers']['roles_mode'])->toBe('any');
});
