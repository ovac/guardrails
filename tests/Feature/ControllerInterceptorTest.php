<?php

use OVAC\Guardrails\Services\ControllerInterceptor;
use OVAC\Guardrails\Services\FlowExtensionBuilder as Flow;
use OVAC\Guardrails\Tests\Fixtures\Post;
use OVAC\Guardrails\Tests\Fixtures\User;

it('intercepts in controller and captures with custom flow', function () {
    $user = User::create(['name' => 'Editor', 'perms' => ['content.publish']]);
    $this->be($user, 'web');
    $post = Post::create(['title' => 'Hello', 'published' => false]);

    $result = ControllerInterceptor::intercept($post, ['published' => true], [
        'only' => ['published'],
        'extender' => Flow::make()->anyOfPermissions(['content.publish'])->toStep(1, 'Editorial'),
    ]);

    expect($result['captured'])->toBeTrue();
    $req = \OVAC\Guardrails\Models\ApprovalRequest::first();
    expect($req)->not->toBeNull();
});
