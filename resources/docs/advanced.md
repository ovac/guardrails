title: Advanced Flows
description: Dynamic flows, risk scoring, conditional steps, and context-aware rules.

# Advanced Flows

Flows can be computed at runtime based on changes, initiator, environment, or any business signal.

## Risk-Based Thresholds

```php
public function guardrailApprovalFlow(array $dirty, string $event): array
{
    $risk = 0;
    if (($dirty['amount'] ?? 0) > 100000) $risk += 2;
    if (($dirty['status'] ?? null) === 'critical') $risk += 1;

    $flow = Flow::make()->anyOfPermissions(['ops.change']);

    if ($risk >= 2) {
        $flow->signedBy(2, 'Ops (High Risk)')->anyOfRoles(['cfo'])->signedBy(1, 'CFO');
    } else {
        $flow->signedBy(1, 'Ops');
    }

    return $flow->build();
}
```

## Attribute-Scoped Rules

Only guard some attributes; let others pass.

```php
$result = $this->guardrailIntercept($model, $changes, [
  'description' => 'Cross-functional review for public flags and pricing.',
  'only' => ['published','price','visibility'],
  'extender' => Flow::make()->anyOfRoles(['editor','ops_manager'])->signedBy(1, 'Review'),
]);
```

## Environment-Aware

```php
if (app()->environment('production')) {
  config(['guardrails.controller.enabled' => true]);
}
```

## Same‑as‑Initiator Overlap

Require a peer sharing at least one permission as the initiator.

```php
Flow::make()
  ->permissions(['settings.update','settings.tune'])
  ->requireAnyPermissions()
  ->samePermissionAsInitiator(true)
  ->includeInitiator(true, true)
  ->signedBy(2, 'Peer Review')
  ->build();
```

## Related Guides

- [Model Guarding Guide](./usage-models.md) — Attach advanced flows directly to models.
- [Controller Interception Guide](./usage-controllers.md) — Apply these patterns to inbound requests.
- [Common Patterns](./patterns.md) — Browse ready-made flow snippets to adapt.
- [Full Testing Guide](./testing-full.md) — Learn how to exercise complex policies in tests.
