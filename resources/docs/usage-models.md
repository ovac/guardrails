title: Model Guarding Guide
description: Use the Guardrail trait to stage changes for approval.

# Model Guarding Guide

Use `OVAC\\Guardrails\\Concerns\\Guardrail` to intercept and stage critical changes on Eloquent models. When an authenticated initiator attempts to modify guarded attributes, Guardrails creates an `ApprovalRequest` with steps and prevents the write. Once the flow completes, Guardrails applies the changes.

## Quick Start

```php
use Illuminate\\Database\\Eloquent\\Model;
use OVAC\\Guardrails\\Concerns\\Guardrail;
use OVAC\\Guardrails\\Services\\Flow;

class EcurrencySetting extends Model
{
    use Guardrail;

    // Choose which attributes require approval
    public function guardrailAttributes(): array
    {
        return ['buy_normal_rate','sell_normal_rate','visible'];
    }

    public function guardrailApprovalDescription(array $dirty, string $event): string
    {
        return 'Finance + ops must approve rate or visibility changes.';
    }

    // Optional: Define a multi-step flow for given changes
    public function guardrailApprovalFlow(array $dirty, string $event): array
    {
        return [
            Flow::make()
                // Uses the configured guard by default
                ->anyOfPermissions(['local_rates.manage'])
                ->includeInitiator(true, true)
                ->signedBy(2, 'Ops Review')
                ->build(),

            [
                'name' => 'Management',
                'threshold' => 1,
                'signers' => [
                    'roles' => ['finance_manager','ops_manager'],
                    'roles_mode' => 'any',
                ],
            ],
        ];
    }
}
```

## How It Works

- The trait hooks into the model’s updating event.
- If there is an authenticated user on the configured guard and guarded attributes changed, Guardrails creates an `ApprovalRequest` in the `pending` state and prevents the write (returns false in the updater).
- When the flow completes (threshold met in the final step), the pending changes are applied to the model in the approvals controller.
- Guardrails stores a human-readable description for audits. Provide one via `guardrailApprovalDescription()` (or define your own helper) to customise the audit trail. If you omit it, Guardrails generates a default summary listing the changed attributes.
- Flows may reference permissions, roles, and initiator overlap. See the [Signing Policy Reference](./signing-policy.md) for details on how Guardrails evaluates each rule.
- Flows may reference permissions, roles, initiator overlap, and even mix different guards per step—see [Controller Interception Guide](./usage-controllers.md#mixing-guards-in-a-flow) for an example.

## Runtime Justifications

Collect a justification from the initiator (for example `approval_description` on a form request) and push it into the Guardrails context before calling `save()`:

```php
$model->guardrails()
    ->description($request->input('approval_description'))
    ->meta(['reason_code' => $request->input('reason_code')]);

$model->fill($request->validated())->save();
```

```php
// Need a different guard for this flow?
$flow = Flow::make()
    ->guard('sanctum')
    ->anyOfPermissions(['api.approve'])
    ->signedBy(1, 'API Review')
    ->build();
```

> For a multi-step example that mixes guards, see the [Controller Interception Guide](./usage-controllers.md#mixing-guards-in-a-flow).

Guardrails clears the context after each capture so subsequent saves start fresh.

## Bypass

You can implement your own logic to bypass capturing by using the controller helper instead, or temporarily turning off the global controller toggle.

## Related Guides

- [Controller Interception Guide](./usage-controllers.md) — Capture approvals when you prefer keeping models pristine.
- [Advanced Flows](./advanced.md) — Generate signing policies dynamically from runtime context.
- [Common Patterns](./patterns.md) — Reuse popular approval configurations.
- [Full Testing Guide](./testing-full.md) — Assert Guardrails behaviour in your application tests.
