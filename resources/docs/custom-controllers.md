title: Using Your Own Controllers
description: How to wire Guardrails without the built-in controller.

# Using Your Own Controllers

You can keep your existing routes/controllers and still use Guardrails to capture and approve changes.

## Capture in Your Update Action

```php
use OVAC\Guardrails\Services\HumanApprovalService as Guardrails;
use OVAC\Guardrails\Services\FlowExtensionBuilder as Flow;

public function update(Request $request, Post $post)
{
    $changes = $request->validate(['published' => 'boolean']);

    // Decide which keys to guard
    $guarded = array_intersect_key($changes, array_flip(['published']));
    if (!empty($guarded)) {
        // Optional: dynamic flow
        $post->humanApprovalFlow = fn () => [
            Flow::make()->rolesAny(['editor','managing_editor'])->toStep(1, 'Editorial Approval')->build(),
        ];

        Guardrails::capture($post, $guarded, 'updating');
        return back()->with('status', 'Submitted for approval.');
    }

    // Apply unguarded changes immediately
    $post->update($changes);
    return back()->with('status', 'Updated.');
}
```

## Approve Endpoint

If you don’t want to use the package controller for approving, add your own route and reuse the policy + models:

```php
use OVAC\Guardrails\Models\ApprovalStep;
use OVAC\Guardrails\Support\SigningPolicy;

public function approve(Request $request, int $requestId, int $stepId)
{
    $request->validate(['comment' => 'nullable|string|max:1000']);
    $user = $request->user(config('guardrails.auth.guard'));
    $step = ApprovalStep::where('request_id', $requestId)->findOrFail($stepId);

    abort_unless($step->status === 'pending', 422, 'Step already finalized');
    abort_unless(SigningPolicy::canSign($user, (array) ($step->meta['signers'] ?? []), $step), 403);

    $sig = \OVAC\Guardrails\Models\ApprovalSignature::updateOrCreate(
        ['step_id' => $step->id, 'staff_id' => $user->id],
        ['decision' => 'approved', 'signed_at' => now(), 'comment' => $request->string('comment')]
    );

    // Complete step/request if threshold reached (same logic as package controller)
    $count = $step->signatures()->where('decision', 'approved')->count();
    if ($count >= (int) $step->threshold) {
        $step->status = 'completed';
        $step->completed_at = now();
        $step->save();

        $allDone = !$step->request->steps()->where('status', 'pending')->exists();
        if ($allDone) {
            $req = $step->request;
            $req->state = 'approved';
            $req->save();

            if ($model = $req->approvable) {
                if (method_exists($model, 'withoutHumanGuard')) {
                    $model->withoutHumanGuard();
                }
                foreach ((array) $req->new_data as $k => $v) {
                    $model->{$k} = $v;
                }
                $model->save();
            }
        }
    }

    return response()->json(['success' => true]);
}
```

This pattern keeps you fully in control of routing and middleware while reusing Guardrails’ core logic.

