title: Advanced Flows
description: Dynamic flows, risk scoring, conditional steps, and context-aware rules.

# Advanced Flows

Flows can be computed at runtime based on changes, actor, environment, or any business signal.

## Risk-Based Thresholds

```php
public function actorApprovalFlow(array $dirty, string $event): array
{
    $risk = 0;
    if (($dirty['amount'] ?? 0) > 100000) $risk += 2;
    if (($dirty['status'] ?? null) === 'critical') $risk += 1;

    $flow = Flow::make()->anyOfPermissions(['ops.change']);

    if ($risk >= 2) {
        $flow->toStep(2, 'Ops (High Risk)')->anyOfRoles(['cfo'])->toStep(1, 'CFO');
    } else {
        $flow->toStep(1, 'Ops');
    }

    return $flow->build();
}
```

## Attribute-Scoped Rules

Only guard some attributes; let others pass.

```php
$result = $this->actorApprovalIntercept($model, $changes, [
  'only' => ['published','price','visibility'],
  'extender' => Flow::make()->anyOfRoles(['editor','ops_manager'])->toStep(1, 'Review'),
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
  ->toStep(2, 'Peer Review')
  ->build();
```
