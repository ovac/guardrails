<?php

/**
 * Unit tests validating the SigningPolicy helper logic.
 */
use OVAC\Guardrails\Support\SigningPolicy;
use OVAC\Guardrails\Tests\Fixtures\User;

it('checks permissions any-of and all-of with spatie-like methods', function () {
    $u = new User(['perms' => ['x', 'y']]);

    // all-of
    expect(SigningPolicy::canSign($u, ['permissions' => ['x', 'y'], 'permissions_mode' => 'all']))->toBeTrue();
    expect(SigningPolicy::canSign($u, ['permissions' => ['x', 'z'], 'permissions_mode' => 'all']))->toBeFalse();

    // any-of
    expect(SigningPolicy::canSign($u, ['permissions' => ['z', 'y'], 'permissions_mode' => 'any']))->toBeTrue();
});

it('checks roles with spatie-like methods', function () {
    $u = new User(['roles' => ['editor']]);
    expect(SigningPolicy::canSign($u, ['roles' => ['editor'], 'roles_mode' => 'all']))->toBeTrue();
    expect(SigningPolicy::canSign($u, ['roles' => ['editor', 'lead'], 'roles_mode' => 'all']))->toBeFalse();
    expect(SigningPolicy::canSign($u, ['roles' => ['contrib', 'editor'], 'roles_mode' => 'any']))->toBeTrue();
});
