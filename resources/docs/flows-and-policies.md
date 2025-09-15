---
title: Flows and Policies
description: Build multi-step flows with any-of/all-of permissions and roles.
tags: [approvals, flow, permissions, roles]
---

# Flows and Policies

Guardrails uses signer rules per step to determine who can approve.

## Any-of vs All-of

```php
use OVAC\\Guardrails\\Services\\FlowExtensionBuilder as Flow;

// Any-of permissions
Flow::make()
  ->permissionsAny(['orders.manage','orders.escalate'])
  ->toStep(1, 'Ops')
  ->build();

// All-of roles (default)
Flow::make()
  ->roles(['ops_manager','finance_manager'])
  ->toStep(1, 'Management')
  ->build();
```

## Counting the Initiator

```php
Flow::make()
  ->permissions(['local_rates.manage'])
  ->includeInitiator(true, true) // include and preapprove initiator
  ->toStep(2, 'Ops Review') // only one other approval needed
  ->build();
```

## Same-as-Initiator Constraints

```php
Flow::make()
  ->permissions(['local_rates.manage'])
  ->requireAnyPermissions()
  ->samePermissionAsInitiator(true)
  ->toStep(2, 'Peer Review')
  ->build();
```

Notes:
- If the initiator lacks the allowed permission/role, the constraint yields no overlap and prevents signing.
- Prefer `includeInitiator(true, true)` without “same-as” when you want initiator to count if eligible, otherwise ignored.

