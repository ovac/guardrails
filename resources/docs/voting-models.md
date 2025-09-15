title: Voting Models
description: Thresholds, consensus, and multi-stage votes.

# Voting Models

Guardrails supports threshold-based voting out of the box. Each step has a `threshold` — the minimum number of approvals required to complete the step.

## Simple Majority

```php
Flow::make()->rolesAny(['architect'])->toStep(3, 'Architecture Vote')->build();
```

## Quorum + Majority (two steps)

```php
Flow::make()
  ->rolesAny(['architect'])
  ->toStep(2, 'Quorum')            // at least 2 must sign
  ->rolesAny(['architect'])
  ->toStep(3, 'Majority')          // then total 3 approvals
  ->build();
```

## Departmental Votes

```php
Flow::make()
  ->rolesAny(['eng_manager','design_manager'])
  ->toStep(2, 'Eng+Design Vote')
  ->build();
```

Notes

- Each signature is stored with the `staff_id` and an optional `comment`.
- Initiator can be included/preapproved; set `includeInitiator(true, true)` on the builder or step meta.

