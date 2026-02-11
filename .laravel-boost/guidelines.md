# Guardrails AI Guidelines

Guardrails is an operational approvals engine for Laravel applications. Use these guidelines to generate idiomatic code and interact with the package effectively.

## Core Concepts

- **Approval Request**: The top-level record for a guarded change.
- **Approval Step**: A level within a multi-step flow.
- **Approval Signature**: A user decision (approve/reject) on a step.
- **Flow**: A series of steps defined fluently using `Flow::make()`.

## Best Practices

### Model Guarding
- Use the `OVAC\Guardrails\Concerns\Guardrail` trait on models.
- Implement `guardrailAttributes()` to specify which attributes requiring approval.
- Implement `guardrailApprovalFlow()` to define the approval logic.

```php
use OVAC\Guardrails\Concerns\Guardrail;
use OVAC\Guardrails\Services\Flow;

class Transaction extends Model
{
    use Guardrail;

    public function guardrailAttributes(): array
    {
        return ['status', 'amount'];
    }

    public function guardrailApprovalFlow(array $dirty, string $event): array
    {
        return [
            Flow::make()
                ->anyOfPermissions(['finance.approve'])
                ->signedBy(1, 'Finance Review')
                ->build(),
        ];
    }
}
```

### Controller Interception
- Use the `InteractsWithGuardrail` trait in controllers.
- Use `guardrailIntercept()` to wrap mutations.

```php
$result = $this->guardrailIntercept($model, $data, [
    'description' => 'Approval required for sensitive update.',
    'flow' => Flow::make()->roles(['admin'])->signedBy(1)->build(),
]);
```

## AI Tools & Flow
- When asked to build a flow, prioritize the `FlowBuilder` fluent API.
- Always include helpful `description` or `meta` hints for reviewers.
- Remind users to publish migrations and config after installation.
