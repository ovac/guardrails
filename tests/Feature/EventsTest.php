<?php

/**
 * Feature tests asserting that Guardrails emits the correct events.
 */
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use OVAC\Guardrails\Events\ApprovalRequestCaptured;
use OVAC\Guardrails\Events\ApprovalRequestCompleted;
use OVAC\Guardrails\Events\ApprovalRequestRejected;
use OVAC\Guardrails\Events\ApprovalStepApproved;
use OVAC\Guardrails\Events\ApprovalStepRejected;
use OVAC\Guardrails\Services\GuardrailApprovalService;
use OVAC\Guardrails\Tests\Fixtures\Post;
use OVAC\Guardrails\Tests\Fixtures\User;

it('fires events on capture and approve', function () {
    $user = User::create([
        'name' => 'Editor',
        'perms' => ['content.publish', 'approvals.manage'],
        'roles' => ['editor'],
    ]);
    $this->be($user, 'web');
    $post = Post::create(['title' => 'Hello', 'published' => false]);

    Event::fake([ApprovalRequestCaptured::class, ApprovalStepApproved::class, ApprovalRequestCompleted::class]);

    $post->update(['published' => true]);
    Event::assertDispatched(ApprovalRequestCaptured::class);

    $req = \OVAC\Guardrails\Models\ApprovalRequest::firstOrFail();
    $step = $req->steps()->firstOrFail();
    $prefix = trim((string) config('guardrails.route_prefix'), '/');
    Gate::define('approvals.manage', fn () => true);
    config([
        'guardrails.permissions.sign' => null,
        'guardrails.middleware' => ['api'],
    ]);

    $this->post('/'.$prefix.'/'.$req->id.'/steps/'.$step->id.'/approve')->assertOk();

    Event::assertDispatched(ApprovalStepApproved::class);
    Event::assertDispatched(ApprovalRequestCompleted::class);
});

it('fires approval events per signature and on threshold', function () {
    $initiator = User::create(['name' => 'Requester', 'perms' => ['content.publish']]);
    $this->be($initiator, 'web');
    $post = Post::create(['title' => 'Hello', 'published' => false]);

    // Capture a guarded update to produce a pending request/step.
    $post->update(['published' => true]);
    $request = \OVAC\Guardrails\Models\ApprovalRequest::firstOrFail();
    $step = $request->steps()->firstOrFail();

    $step->threshold = 2;
    $step->meta = array_merge((array) ($step->meta ?? []), [
        'signers' => [
            'permissions' => ['approvals.manage'],
            'permissions_mode' => 'all',
        ],
    ]);
    $step->save();

    $approverOne = User::create(['name' => 'Reviewer A', 'perms' => ['approvals.manage']]);
    $approverTwo = User::create(['name' => 'Reviewer B', 'perms' => ['approvals.manage']]);

    Event::fake([ApprovalStepApproved::class, ApprovalRequestCompleted::class]);

    $prefix = trim((string) config('guardrails.route_prefix'), '/');

    $this->actingAs($approverOne, 'web')
        ->post('/'.$prefix.'/'.$request->id.'/steps/'.$step->id.'/approve')
        ->assertOk();

    Event::assertDispatchedTimes(ApprovalStepApproved::class, 1);
    Event::assertDispatchedTimes(ApprovalRequestCompleted::class, 0);

    $step->refresh();
    expect($step->status)->toBe('pending');

    $this->actingAs($approverTwo, 'web')
        ->post('/'.$prefix.'/'.$request->id.'/steps/'.$step->id.'/approve')
        ->assertOk();

    Event::assertDispatchedTimes(ApprovalStepApproved::class, 2);
    Event::assertDispatchedTimes(ApprovalRequestCompleted::class, 1);

    $step->refresh();
    $request->refresh();
    expect($step->status)->toBe('completed');
    expect($request->state)->toBe('approved');
});

it('fires rejection events per signature and on threshold', function () {
    $initiator = User::create(['name' => 'Requester', 'perms' => ['content.publish']]);
    $this->be($initiator, 'web');
    $post = Post::create(['title' => 'Hello', 'published' => false]);

    // Capture a guarded update to produce a pending request/step.
    $post->update(['published' => true]);
    $request = \OVAC\Guardrails\Models\ApprovalRequest::firstOrFail();
    $step = $request->steps()->firstOrFail();

    $step->meta = array_merge((array) ($step->meta ?? []), [
        'signers' => [
            'permissions' => ['approvals.manage'],
            'permissions_mode' => 'any',
        ],
        'rejection_min' => 2,
    ]);
    $step->save();

    $signerOne = User::create(['name' => 'Reviewer A', 'perms' => ['approvals.manage']]);
    $signerTwo = User::create(['name' => 'Reviewer B', 'perms' => ['approvals.manage']]);

    Event::fake([ApprovalStepRejected::class, ApprovalRequestRejected::class]);

    GuardrailApprovalService::rejectStep($step->fresh(), $signerOne);

    Event::assertDispatchedTimes(ApprovalStepRejected::class, 1);
    Event::assertDispatchedTimes(ApprovalRequestRejected::class, 0);

    $stepEvents = Event::dispatched(ApprovalStepRejected::class);
    expect($stepEvents)->toHaveCount(1);
    $firstEvent = $stepEvents[0][0];
    expect($firstEvent->step->id)->toBe($step->id);
    expect($firstEvent->step->status)->toBe('pending');

    GuardrailApprovalService::rejectStep($step->fresh(), $signerTwo);

    Event::assertDispatchedTimes(ApprovalStepRejected::class, 2);
    Event::assertDispatchedTimes(ApprovalRequestRejected::class, 1);

    $stepEvents = Event::dispatched(ApprovalStepRejected::class);
    expect($stepEvents)->toHaveCount(2);
    $secondEvent = $stepEvents[1][0];
    expect($secondEvent->step->status)->toBe('rejected');

    $requestEvents = Event::dispatched(ApprovalRequestRejected::class);
    expect($requestEvents)->toHaveCount(1);
    $rejectedEvent = $requestEvents[0][0];
    expect($rejectedEvent->request->id)->toBe($request->id);
    expect($rejectedEvent->request->state)->toBe('rejected');

    $step->refresh();
    $request->refresh();
    expect($step->status)->toBe('rejected');
    expect($request->state)->toBe('rejected');
});
