<?php

use Illuminate\Support\Facades\Event;
use OVAC\Guardrails\Events\ApprovalRequestCaptured;
use OVAC\Guardrails\Events\ApprovalRequestCompleted;
use OVAC\Guardrails\Events\ApprovalStepApproved;
use OVAC\Guardrails\Tests\Fixtures\Post;
use OVAC\Guardrails\Tests\Fixtures\User;

it('fires events on capture and approve', function () {
    $user = User::create(['name' => 'Editor', 'perms' => ['content.publish']]);
    $this->be($user, 'web');
    $post = Post::create(['title' => 'Hello', 'published' => false]);

    Event::fake([ApprovalRequestCaptured::class, ApprovalStepApproved::class, ApprovalRequestCompleted::class]);

    $post->update(['published' => true]);
    Event::assertDispatched(ApprovalRequestCaptured::class);

    $req = \OVAC\Guardrails\Models\ApprovalRequest::firstOrFail();
    $step = $req->steps()->firstOrFail();
    $prefix = trim((string) config('guardrails.route_prefix'), '/');
    $this->post('/'.$prefix.'/'.$req->id.'/steps/'.$step->id.'/approve')->assertOk();

    Event::assertDispatched(ApprovalStepApproved::class);
    Event::assertDispatched(ApprovalRequestCompleted::class);
});

