<?php

use Illuminate\Support\Facades\Event;
use OVAC\Guardrails\Events\ApprovalRequestCaptured;
use OVAC\Guardrails\Tests\Fixtures\Post;
use OVAC\Guardrails\Tests\Fixtures\User;

it('captures guarded updates and prevents immediate write', function () {
    $user = User::create(['name' => 'Editor', 'perms' => ['content.publish']]);
    $post = Post::create(['title' => 'Hello', 'published' => false]);

    $this->be($user, 'web');

    Event::fake([ApprovalRequestCaptured::class]);

    // Updating should be intercepted by the trait (returns false)
    $result = $post->update(['published' => true]);
    expect($result)->toBeFalse();
    $post->refresh();
    expect($post->published)->toBeFalse();

    Event::assertDispatched(ApprovalRequestCaptured::class);

    // Verify an approval request exists
    $req = \OVAC\Guardrails\Models\ApprovalRequest::first();
    expect($req)->not->toBeNull();
});

