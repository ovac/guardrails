title: Controller Interception Guide
description: Intercept mutations without modifying your models.

# Controller Interception Guide

Use `OVAC\\Guardrails\\Concerns\\InteractsWithActorApproval` in your controller to route critical mutations through Guardrails without touching models.

```php
use OVAC\\Guardrails\\Concerns\\InteractsWithActorApproval;
use OVAC\\Guardrails\\Services\\Flow;

class OrdersController extends Controller
{
    use InteractsWithActorApproval;

    public function update(UpdateOrderRequest $request, Order $order)
    {
        $changes = $request->validated();

$result = $this->actorApprovalIntercept($order, $changes, [
            'only' => ['status_id'], // only guard status changes
            'extender' => Flow::make()
                ->anyOfPermissions(['orders.manage','orders.escalate'])
                ->includeInitiator(true, true)
                ->toStep(2, 'Ops'),
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
