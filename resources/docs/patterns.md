---
title: Common Patterns
description: Two-man rule, peer review, role fallback, and more.
tags: [patterns, recipes, approvals]
---

# Common Patterns

## Two-Man Rule (initiator + one peer)

```php
Flow::make()
  ->permissionsAny(['orders.manage'])
  ->includeInitiator(true, true)
  ->toStep(2, 'Ops Two-Man')
  ->build();
```

## One of Any Two Roles

```php
Flow::make()
  ->rolesAny(['finance_manager','ops_manager'])
  ->toStep(1, 'Management')
  ->build();
```

## Multi-Step Escalation

```php
Flow::make()
  ->permissionsAny(['local_rates.manage'])
  ->includeInitiator(true, true)
  ->toStep(2, 'Ops')
  ->rolesAny(['finance_manager','ops_manager'])
  ->toStep(1, 'Management')
  ->build();
```

## Controller Intercept Without Touching Models

```php
$changes = $request->validate(['status_id' => 'integer']);

$result = $this->humanApprovalIntercept($model, $changes, [
  'only' => ['status_id'],
  'extender' => Flow::make()->permissionsAny(['orders.manage','orders.escalate'])->toStep(2, 'Ops'),
]);
```

