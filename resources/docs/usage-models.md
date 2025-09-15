title: Model Guarding Guide
description: Use the ActorGuarded trait to stage changes for approval.

# Model Guarding Guide

Use `OVAC\\Guardrails\\Concerns\\ActorGuarded` to intercept and stage critical changes on Eloquent models. When an authenticated actor attempts to modify guarded attributes, Guardrails creates an `ApprovalRequest` with steps and prevents the write. Once the flow completes, Guardrails applies the changes.

## Quick Start

```php
use Illuminate\\Database\\Eloquent\\Model;
use OVAC\\Guardrails\\Concerns\\ActorGuarded;
use OVAC\\Guardrails\\Services\\Flow;

class EcurrencySetting extends Model
{
    use ActorGuarded;

    // Choose which attributes require approval
    public function humanGuardAttributes(): array
    {
        return ['buy_normal_rate','sell_normal_rate','visible'];
    }

    // Optional: Define a multi-step flow for given changes
    public function actorApprovalFlow(array $dirty, string $event): array
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
- When the flow completes (threshold met in the final step), the pending changes are applied to the model in the approvals controller.

## Bypass

You can implement your own logic to bypass capturing by using the controller helper instead, or temporarily turning off the global controller toggle.
