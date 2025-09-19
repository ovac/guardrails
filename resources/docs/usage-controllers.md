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

## Related Guides

- [Model Guarding Guide](./usage-models.md) — Let your Eloquent models stage approvals automatically.
- [Using Your Own Controllers](./custom-controllers.md) — Swap in bespoke routes while reusing Guardrails internals.
- [Advanced Flows](./advanced.md) — Compose complex policies for controller captures.
- [Full Testing Guide](./testing-full.md) — Validate interceptor behaviour with Pest and Testbench.
