title: Database & Migrations
description: Tables created by Guardrails and important columns.

# Database & Migrations

Publish the migrations and run them:

```bash
php artisan vendor:publish --provider="OVAC\\Guardrails\\GuardrailsServiceProvider" --tag=guardrails-migrations
php artisan migrate
```

Tables

1) `human_approval_requests`

- `id` — Primary key
- `approvable_type`, `approvable_id` — Morph target of the change
- `actor_id` — User who initiated the change (from your configured guard)
- `state` — pending|approved|rejected (package uses `pending` and `approved`)
- `new_data` — JSON snapshot of proposed values
- `original_data` — JSON snapshot of original values
- `context` — JSON route/event metadata
- `meta` — JSON for future extensions
- `created_at`, `updated_at`

2) `human_approval_steps`

- `id`, `request_id` — Belongs to an approval request
- `level` — Step order (1-based)
- `name` — Display name of the step
- `threshold` — Minimum approvals required for this step
- `status` — pending|completed
- `completed_at` — Timestamp when threshold met
- `meta` — JSON including `signers`, `include_initiator`, `preapprove_initiator`
- `created_at`, `updated_at`

3) `human_approval_signatures`

- `id`, `step_id` — Belongs to a step
- `staff_id` — Signer staff
- `decision` — approved|rejected|postponed (package uses `approved`)
- `comment` — Optional signer comment
- `signed_at` — Timestamp of the decision
- `meta` — JSON for extensions
- `created_at`, `updated_at`

Relationships

- `ApprovalRequest` hasMany `ApprovalStep`
- `ApprovalStep` hasMany `ApprovalSignature`
- `ApprovalSignature` belongsTo `ApprovalStep`
- `ApprovalRequest` morphTo `approvable`
