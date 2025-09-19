title: Database & Migrations
description: Tables created by Guardrails and important columns.

# Database & Migrations

Publish the migrations and run them:

```bash
php artisan vendor:publish --provider="OVAC\\Guardrails\\GuardrailsServiceProvider" --tag=guardrails-migrations
php artisan migrate
```

Tables

1) `guardrail_approval_requests`

- `id` — Primary key
- `approvable_type`, `approvable_id` — Morph target of the change
- `initiator_id` — User who initiated the change (from your configured guard)
- `state` — pending|approved|rejected (package now uses all three)
- `description` — Short human summary of the captured change
- `new_data` — JSON snapshot of proposed values
- `original_data` — JSON snapshot of original values
- `context` — JSON route/event metadata
- `meta` — JSON for future extensions
- `created_at`, `updated_at`

2) `guardrail_approval_steps`

- `id`, `request_id` — Belongs to an approval request
- `level` — Step order (1-based)
- `name` — Display name of the step
- `threshold` — Minimum approvals required for this step
- `status` — pending|completed|rejected
- `completed_at` — Timestamp when threshold met
- `meta` — JSON including `signers`, `include_initiator`, `preapprove_initiator`, `rejection_min`, `rejection_max`
- `created_at`, `updated_at`

3) `guardrail_approval_signatures`

- `id`, `step_id` — Belongs to a step
- `signer_id` — Signer user id
- `decision` — approved|rejected|postponed (package uses `approved` and `rejected`)
- `comment` — Optional signer comment captured for approvals and rejections
- `signed_at` — Timestamp of the decision
- `meta` — JSON for extensions
- `created_at`, `updated_at`

Relationships

- `ApprovalRequest` hasMany `ApprovalStep`
- `ApprovalStep` hasMany `ApprovalSignature`
- `ApprovalSignature` belongsTo `ApprovalStep`
- `ApprovalRequest` morphTo `approvable`
