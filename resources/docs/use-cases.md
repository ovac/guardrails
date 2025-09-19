title: Use Cases
description: Practical end-to-end scenarios across teams.

# Use Cases

This page shows how Guardrails fits different teams with simple, copy‑pasteable flows.

## Content Publishing (Marketing)

Two‑man rule to publish a blog post: author + any editor.

```php
Flow::make()
  ->anyOfPermissions(['content.publish'])
  ->includeInitiator(true, true)   // author pre‑approved
  ->signedBy(2, 'Editorial Review')  // needs one editor
  ->build();
```

## Discount Campaign (Sales)

Approve discounts based on depth: Sales lead under 20%, VP if 20%+.

```php
// Compute at runtime
$depth = (int) $changes['discount_percent'];

return $depth < 20
  ? Flow::make()->anyOfRoles(['sales_lead'])->signedBy(1, 'Sales Approval')->build()
  : Flow::make()->anyOfRoles(['vp_sales'])->signedBy(1, 'VP Approval')->build();
```

## Product Rollout (Product + Engineering)

Ops approves feature flag, then Engineering Lead approves rollout.

```php
Flow::make()
  ->anyOfRoles(['ops_manager'])
  ->signedBy(1, 'Ops Gate')
  ->anyOfRoles(['eng_lead'])
  ->signedBy(1, 'Engineering Gate')
  ->build();
```

## Legal & Security (Compliance)

Any of Legal OR Security must sign before publishing policy updates.

```php
Flow::make()
  ->anyOfRoles(['legal_counsel','security_officer'])
  ->signedBy(1, 'Compliance Review')
  ->build();
```

## Finance Approval (Payouts)

Two approvals in Finance; initiator cannot be the only one.

```php
Flow::make()
  ->permissions(['payouts.approve'])
  ->requireAnyPermissions()        // count any of the listed perms
  ->includeInitiator(true, true)   // initiator counts
  ->signedBy(2, 'Finance Double‑Sign')
  ->build();
```

## Voting (RFCs, Decisions)

Require 3 votes out of 5 architects.

```php
Flow::make()
  ->anyOfRoles(['architect'])
  ->signedBy(3, 'Architecture Vote')
  ->build();
```

## High‑Risk Changes (Multi‑step Escalation)

Ops must approve; if amount > 100k, add CFO.

```php
$flow = Flow::make()
  ->anyOfPermissions(['ops.change'])
  ->signedBy(1, 'Ops');

if (($changes['amount'] ?? 0) > 100000) {
  $flow->anyOfRoles(['cfo'])->signedBy(1, 'CFO');
}

return $flow->build();
```
