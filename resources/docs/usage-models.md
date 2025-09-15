title: Model Guarding Guide
description: Use the HumanGuarded trait to stage changes for approval.

# Model Guarding Guide

Use `OVAC\\Guardrails\\Concerns\\HumanGuarded` to intercept and stage critical changes on Eloquent models. When an authenticated staff user attempts to modify guarded attributes, Guardrails creates an `ApprovalRequest` with steps and prevents the write. Once the flow completes, Guardrails applies the changes.

## Quick Start

```php
use Illuminate\\Database\\Eloquent\\Model;
use OVAC\\Guardrails\\Concerns\\HumanGuarded;
use OVAC\\Guardrails\\Services\\FlowExtensionBuilder as Flow;

class EcurrencySetting extends Model
{
    use HumanGuarded;

    // Choose which attributes require approval
    public function humanGuardAttributes(): array
    {
        return ['buy_normal_rate','sell_normal_rate','visible'];
    }

    // Optional: Define a multi-step flow for given changes
    public function humanApprovalFlow(array $dirty, string $event): array
    {
        return [
            Flow::make()
                ->anyOfPermissions(['local_rates.manage'])
                ->includeInitiator(true, true)
                ->toStep(2, 'Ops Review')
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
- If there is an authenticated `staff` user and guarded attributes changed, Guardrails creates an `ApprovalRequest` in the `pending` state and prevents the write (returns false in the updater).
- When the flow completes (threshold met in the final step), the pending changes are applied to the model in `HumanApprovalsController`.

## Bypass

You can implement your own logic to bypass capturing by using the controller helper instead, or temporarily turning off the global controller toggle.
