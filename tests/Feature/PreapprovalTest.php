<?php

use OVAC\Guardrails\Tests\Fixtures\PrePost;
use OVAC\Guardrails\Tests\Fixtures\User;

it('preapproves initiator when configured and completes step', function () {
    $user = User::create(['name' => 'Editor', 'perms' => ['content.publish']]);
    $this->be($user, 'web');

    $post = PrePost::create(['title' => 'X', 'published' => false]);
    $post->update(['published' => true]);

    $req = \OVAC\Guardrails\Models\ApprovalRequest::firstOrFail();
    $step = $req->steps()->firstOrFail();
    expect($step->status)->toBe('completed');
});

