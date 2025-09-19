title: Auditing & Changelog
description: Track who changed what, when, and why.

# Auditing & Changelog

Guardrails stores original and proposed values along with who initiated and who signed. You can also listen to package events to write custom audit trails.

## Built-in Data

- `approval_requests` — `initiator_id`, `new_data`, `original_data`, `context` (includes route name and event)
- `approval_steps` — `level`, `threshold`, `status`, `completed_at`, `meta.signers`
- `approval_signatures` — `signer_id` (approver user id), `decision`, `comment`, `signed_at`

## Events

- `ApprovalRequestCaptured($request)` — when a request is created.
- `ApprovalStepApproved($step, $signature)` — when an approval signature is recorded.
- `ApprovalStepRejected($step, $signature)` — whenever a rejection signature is recorded (the step may still be pending until the threshold is met).
- `ApprovalRequestCompleted($request)` — after all steps complete and changes are applied.
- `ApprovalRequestRejected($request, $step, $signature)` — when a request is rejected at any step after the rejection threshold is met.

### Example: Persist an Audit Trail

```php
Event::listen(\OVAC\Guardrails\Events\ApprovalRequestCaptured::class, function ($e) {
    Audit::record('approval.captured', [
        'request_id' => $e->request->id,
        'initiator_id' => $e->request->initiator_id,
        'approvable' => [$e->request->approvable_type, $e->request->approvable_id],
        'new' => $e->request->new_data,
        'original' => $e->request->original_data,
        'context' => $e->request->context,
    ]);
});

Event::listen(\OVAC\Guardrails\Events\ApprovalStepApproved::class, function ($e) {
    Audit::record('approval.step.approved', [
        'request_id' => $e->step->request_id,
        'step_id' => $e->step->id,
        'signer_id' => $e->signature->signer_id,
        'comment' => $e->signature->comment,
        'signed_at' => $e->signature->signed_at,
    ]);
});

Event::listen(\OVAC\Guardrails\Events\ApprovalStepRejected::class, function ($e) {
    Audit::record('approval.step.rejected', [
        'request_id' => $e->step->request_id,
        'step_id' => $e->step->id,
        'signer_id' => $e->signature->signer_id,
        'comment' => $e->signature->comment,
        'signed_at' => $e->signature->signed_at,
    ]);
});

Event::listen(\OVAC\Guardrails\Events\ApprovalRequestCompleted::class, function ($e) {
    Audit::record('approval.completed', [
        'request_id' => $e->request->id,
        'applied_at' => now(),
    ]);
});

Event::listen(\OVAC\Guardrails\Events\ApprovalRequestRejected::class, function ($e) {
    Audit::record('approval.rejected', [
        'request_id' => $e->request->id,
        'step_id' => $e->step->id,
        'signer_id' => $e->signature->signer_id,
        'comment' => $e->signature->comment,
        'signed_at' => $e->signature->signed_at,
    ]);
});
```

## Changelog Output

To generate human-friendly notes for accounting/accountability:

- Render differences between `original_data` and `new_data` per request.
- Include step names, signers (lookup by `signer_id`/user id), timestamps, and comments.
- Append the approval request ID in deployment or release notes.
