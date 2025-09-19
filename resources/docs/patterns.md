---
title: Common Patterns
description: Two-man rule, peer review, role fallback, and more.
tags: [patterns, recipes, approvals]
---

# Common Patterns

## Two-Man Rule (initiator + one peer)

```php
Flow::make()
  ->anyOfPermissions(['orders.manage'])
  ->includeInitiator(true, true)
  ->signedBy(2, 'Ops Two-Man')
  ->build();
```

## One of Any Two Roles

```php
Flow::make()
  ->anyOfRoles(['finance_manager','ops_manager'])
  ->signedBy(1, 'Management')
  ->build();
```

## Multi-Step Escalation

```php
Flow::make()
  ->anyOfPermissions(['local_rates.manage'])
  ->includeInitiator(true, true)
  ->signedBy(2, 'Ops')
  ->anyOfRoles(['finance_manager','ops_manager'])
  ->signedBy(1, 'Management')
  ->build();
```

## Controller Intercept Without Touching Models

```php
$changes = $request->validate(['status_id' => 'integer']);

$result = $this->guardrailIntercept($model, $changes, [
  'description' => 'Escalate order status overrides to operations.',
  'only' => ['status_id'],
  'extender' => Flow::make()->anyOfPermissions(['orders.manage','orders.escalate'])->signedBy(2, 'Ops'),
]);
```

## Related Guides

- [Model Guarding Guide](./usage-models.md) — Implement these recipes on your Eloquent models.
- [Controller Interception Guide](./usage-controllers.md) — Adapt patterns to request interception.
- [Advanced Flows](./advanced.md) — Extend patterns with dynamic logic.
- [Full Testing Guide](./testing-full.md) — Confirm each recipe behaves as expected.
