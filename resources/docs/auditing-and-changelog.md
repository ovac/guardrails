title: Auditing & Changelog
description: Track who changed what, when, and why.

# Auditing & Changelog

Guardrails stores original and proposed values along with who initiated and who signed. You can also listen to package events to write custom audit trails.

## Built-in Data

- `approval_requests` — `actor_staff_id`, `new_data`, `original_data`, `context` (includes route name and event)
- `approval_steps` — `level`, `threshold`, `status`, `completed_at`, `meta.signers`
- `approval_signatures` — `staff_id`, `decision`, `comment`, `signed_at`

## Events

- `ApprovalRequestCaptured($request)` — when a request is created.
- `ApprovalStepApproved($step, $signature)` — when a signature is recorded.
- `ApprovalRequestCompleted($request)` — after all steps complete and changes are applied.

### Example: Persist an Audit Trail

```php
Event::listen(\OVAC\Guardrails\Events\ApprovalRequestCaptured::class, function ($e) {
    Audit::record('approval.captured', [
        'request_id' => $e->request->id,
        'actor_staff_id' => $e->request->actor_staff_id,
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
        'staff_id' => $e->signature->staff_id,
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
```

## Changelog Output

To generate human-friendly notes for accounting/accountability:

- Render differences between `original_data` and `new_data` per request.
- Include step names, signers (by `staff_id` lookup), timestamps, and comments.
- Append the approval request ID in deployment or release notes.

