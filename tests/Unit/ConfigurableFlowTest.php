<?php

use OVAC\Guardrails\Support\ConfigurableFlow;

it('returns fallback when config flow is missing', function () {
    config(['guardrails.flows' => []]);

    $fallback = [
        ['name' => 'Fallback', 'threshold' => 1, 'signers' => [], 'meta' => []],
    ];

    $resolved = ConfigurableFlow::resolve('orders.approve', $fallback, ['summary' => 'hello']);

    expect($resolved)->toBe($fallback)
        ->and($resolved[0]['meta']['summary'])->toBe('hello');
});

it('merges meta defaults into configured flow', function () {
    config([
        'guardrails.flows' => [
            'orders' => [
                'approve' => [
                    ['name' => 'Ops', 'threshold' => 1, 'signers' => [], 'meta' => []],
                ],
            ],
        ],
    ]);

    $resolved = ConfigurableFlow::resolve('orders.approve', null, ['summary' => 'order update']);

    expect($resolved)->toBeArray()
        ->and($resolved[0]['name'])->toBe('Ops')
        ->and($resolved[0]['meta']['summary'])->toBe('order update');
});

it('ignores meta defaults when step already defines key', function () {
    config([
        'guardrails.flows' => [
            'orders' => [
                'approve' => [
                    ['name' => 'Ops', 'threshold' => 1, 'signers' => [], 'meta' => ['summary' => 'custom']],
                ],
            ],
        ],
    ]);

    $resolved = ConfigurableFlow::resolve('orders.approve', null, ['summary' => 'should-not-override']);

    expect($resolved[0]['meta']['summary'])->toBe('custom');
});

it('resolves flat dot keys in config', function () {
    config([
        'guardrails.flows' => [
            'orders.approve' => [
                ['name' => 'Flat Ops', 'threshold' => 1, 'signers' => [], 'meta' => []],
            ],
        ],
    ]);

    $resolved = ConfigurableFlow::resolve('orders.approve', null, ['summary' => 'flat config']);

    expect($resolved)->toBeArray()
        ->and($resolved[0]['name'])->toBe('Flat Ops')
        ->and($resolved[0]['meta']['summary'])->toBe('flat config');
});

it('wraps single-step associative arrays automatically', function () {
    config([
        'guardrails.flows' => [
            'orders.approve' => [
                'name' => 'Single Step',
                'threshold' => 1,
                'signers' => [],
            ],
        ],
    ]);

    $resolved = ConfigurableFlow::resolve('orders.approve', null, ['summary' => 'auto-wrapped']);

    expect($resolved)->toBeArray()
        ->and($resolved[0]['name'])->toBe('Single Step')
        ->and($resolved[0]['meta']['summary'])->toBe('auto-wrapped');
});

it('detects presence of configured flow', function () {
    config([
        'guardrails.flows' => [
            'orders' => ['approve' => [['name' => 'x']]],
        ],
    ]);

    expect(ConfigurableFlow::has('orders.approve'))->toBeTrue()
        ->and(ConfigurableFlow::has('orders.reject'))->toBeFalse();
});
