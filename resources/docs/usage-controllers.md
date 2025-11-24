title: Controller Interception Guide
description: Intercept mutations without modifying your models.

# Controller Interception Guide

Use `OVAC\\Guardrails\\Http\\Concerns\\InteractsWithGuardrail` in your controller to route critical mutations through Guardrails without touching models.

> Guardrails uses the guard defined in `config('guardrails.auth.guard')`, falling back to `auth.defaults.guard`. Make sure that guard is authenticated before calling the interceptor.

## Mixing Guards in a Flow

You can override the guard per step by chaining `guard()` within the flow builder:

```php
$result = $this->guardrailIntercept($order, $changes, [
    'extender' => Flow::make()
        ->guard('web')
        ->anyOfPermissions(['orders.review'])
        ->signedBy(1, 'Ops')
        ->guard('api')
        ->anyOfPermissions(['finance.sign'])
        ->signedBy(1, 'Finance')
        ->build(),
]);
```

Each call to `guard()` applies to the current in-progress step, allowing cross-guard approval chains.

```php
use OVAC\\Guardrails\\Http\\Concerns\\InteractsWithGuardrail;
use OVAC\\Guardrails\\Services\\Flow;

class OrdersController extends Controller
{
    use InteractsWithGuardrail;

    public function update(UpdateOrderRequest $request, Order $order)
    {
        $changes = $request->validated();

        $result = $this->guardrailIntercept($order, $changes, [
            'description' => 'Escalate risky order status changes to ops.',
            'only' => ['status_id'], // only guard status changes
            'extender' => Flow::make()
                ->anyOfPermissions(['orders.manage','orders.escalate'])
                ->includeInitiator(true, true)
                ->signedBy(2, 'Ops'),
        ]);

        if ($result['captured']) {
            return back()->with('status', 'Submitted for approval.');
        }

        $order->update($changes);
        return back()->with('status', 'Updated.');
    }
}
```

Options

- event: creating|updating|custom (default updating)
- only: array attribute keys to guard (overrides model rules)
- except: array attribute keys to ignore
- flow: array preset flow (overrides model flow)
- extender: `FlowExtender` to build a flow fluently
- description: summary persisted on the approval request
- meta: array of extra request metadata stored server-side
- Signer rules in your flow can use permissions, roles, and initiator overlap. Consult the [Signing Policy Reference](./signing-policy.md) for evaluation order and customization tips.

### Configurable flows from config (drop-in)

Let ops tweak steps without shipping code. `guardrailFlow()` checks `guardrails.flows.<feature>.<action>` first (flat dot key or nested arrays), falls back to your coded flow, and merges meta defaults like `summary` onto every step. Single-step shorthand works too (no double brackets).

```php
// config/guardrails.php (optional override)
'flows' => [
    // flat dot key (clean)
    'orders.approve' => [[
        'name' => 'Ops Review',
        'threshold' => 1,
        'signers' => [
            'guard' => 'web',
            'permissions' => ['orders.approve'],
            'permissions_mode' => 'any',
            'roles' => [],
            'roles_mode' => 'all',
        ],
        'meta' => [
            'include_initiator' => false,
            'preapprove_initiator' => true,
        ],
    ]],

    // single-step shorthand (auto-wrapped)
    // 'orders.approve' => [
    //     'name' => 'Ops Review',
    //     'threshold' => 1,
    //     'signers' => ['guard' => 'web', 'permissions' => ['orders.approve']],
    // ],
],
```

```php
// Controller
use OVAC\Guardrails\Services\Flow;

$flow = $this->guardrailFlow(
    'orders.approve',
    Flow::make()->anyOfPermissions(['orders.approve'])->includeInitiator(true, true)->signedBy(2, 'Ops')->build(),
    ['summary' => 'Order approval for #'.$order->id]
);

$result = $this->guardrailIntercept($order, $changes, [
    'description' => 'Approval required to confirm this order.',
    'only' => ['status'],
    'flow' => $flow,
]);
```

## Related Guides

- [Model Guarding Guide](./usage-models.md) — Let your Eloquent models stage approvals automatically.
- [Using Your Own Controllers](./custom-controllers.md) — Swap in bespoke routes while reusing Guardrails internals.
- [Advanced Flows](./advanced.md) — Compose complex policies for controller captures.
- [Full Testing Guide](./testing-full.md) — Validate interceptor behaviour with Pest and Testbench.
