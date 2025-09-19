<?php

/**
 * Feature tests for the approvals index API response payload.
 */
use OVAC\Guardrails\Tests\Fixtures\Post;
use OVAC\Guardrails\Tests\Fixtures\User;

it('returns signing permissions for each step in approvals index', function () {
    $user = User::create([
        'name' => 'Editor',
        'perms' => ['content.publish', 'approvals.manage'],
        'roles' => ['editor'],
    ]);

    $post = Post::create(['title' => 'Preview', 'published' => false]);

    $this->be($user, 'web');

    // Trigger a guarded update so an approval request is generated.
    $post->update(['published' => true]);

    $prefix = '/'.trim((string) config('guardrails.route_prefix', 'guardrails/api'), '/');

    $response = $this->actingAs($user, 'web')->getJson($prefix);

    $response->assertOk();

    $stepPermissions = $response->json('data.data.0.steps.0.permissions_required');
    expect($stepPermissions)->toBe(['content.publish']);

    $stepMode = $response->json('data.data.0.steps.0.permissions_mode');
    expect($stepMode)->toBe('any');

    $stepRoles = $response->json('data.data.0.steps.0.roles_required');
    expect($stepRoles)->toBe(['editor']);

    $roleMode = $response->json('data.data.0.steps.0.roles_mode');
    expect($roleMode)->toBe('any');
});
