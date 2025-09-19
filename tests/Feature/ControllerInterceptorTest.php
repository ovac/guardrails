<?php

/**
 * Feature tests for the controller interceptor helper.
 */
use OVAC\Guardrails\Services\ControllerInterceptor;
use OVAC\Guardrails\Services\Flow;
use OVAC\Guardrails\Tests\Fixtures\Post;
use OVAC\Guardrails\Tests\Fixtures\User;

it('intercepts in controller and captures with custom flow', function () {
    $user = User::create(['name' => 'Editor', 'perms' => ['content.publish']]);
    $this->be($user, 'web');
    $post = Post::create(['title' => 'Hello', 'published' => false]);

    $result = ControllerInterceptor::intercept($post, ['published' => true], [
        'only' => ['published'],
        'extender' => Flow::make()
            ->guard('web')
            ->anyOfPermissions(['content.publish'])
            ->signedBy(1, 'Editorial')
            ->guard('api')
            ->anyOfPermissions(['finance.approve'])
            ->signedBy(1, 'Finance')
            ->build(),
    ]);

    expect($result['captured'])->toBeTrue();
    $req = \OVAC\Guardrails\Models\ApprovalRequest::first();
    expect($req)->not->toBeNull();
});
