<?php

use Illuminate\Support\Facades\Event;
use OVAC\Guardrails\Events\ApprovalRequestCompleted;
use OVAC\Guardrails\Tests\Fixtures\Post;
use OVAC\Guardrails\Tests\Fixtures\User;

it('approves a step via API and applies changes', function () {
    $user = User::create(['name' => 'Editor', 'perms' => ['content.publish']]);
    $post = Post::create(['title' => 'Hello', 'published' => false]);
    $this->be($user, 'web');

    // Capture a change
    $post->update(['published' => true]);
    $req = \OVAC\Guardrails\Models\ApprovalRequest::firstOrFail();
    $step = $req->steps()->firstOrFail();

    Event::fake([ApprovalRequestCompleted::class]);

    // Approve via HTTP route
    $prefix = trim((string) config('guardrails.route_prefix'), '/');
    $resp = $this->post('/'.$prefix.'/'.$req->id.'/steps/'.$step->id.'/approve');
    $resp->assertOk();

    Event::assertDispatched(ApprovalRequestCompleted::class);

    // Verify request state and model updated
    $req->refresh();
    expect($req->state)->toBe('approved');
    $post->refresh();
    expect($post->published)->toBeTrue();
});

