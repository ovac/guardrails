title: Using Your Own Controllers
description: Opinionated patterns for teams that want Guardrails logic without the stock controller.

# Using Your Own Controllers

Guardrails ships with `GuardrailApprovalsController`, but you do not have to expose its routes. Many teams already have established API envelopes, documentation styles, or tenancy rules that they would rather keep. This guide documents the supported ways to plug Guardrails into your own controllers, the scenarios in which each pattern shines, and every hook you can override when extending the stock controller.

---

## When Should You Roll Your Own?

| Scenario | Goal | Recommended Pattern |
| --- | --- | --- |
| Existing REST/JSON API with consistent envelopes | Keep response shape, docblocks, rate-limits, and middleware | **Extend `GuardrailApprovalsController`** and override response hooks |
| Domain service or console workflow | Capture approvals from outside HTTP | **Call `GuardrailApprovalService::capture()` / `::rejectStep()` directly** |
| Multi-tenant dashboards with custom queries | Restrict listings and actions to tenant boundaries | **Create bespoke controllers** that reuse Guardrails models + services |
| Hybrid flows (bot, email, admin UI) | Approve/reject from different channels | Combine **service helpers** with lightweight routes |

---

## Pattern 1 – Capture Inside Your Business Action

You can request approval while processing the original action—no controller changes required. Capture only the attributes that should be guarded and return early so the UI informs users that an approval is pending.

```php
use OVAC\Guardrails\Services\Flow;
use OVAC\Guardrails\Services\GuardrailApprovalService as Guardrails;

public function update(Request $request, Post $post)
{
    $changes = $request->validate(['published' => 'boolean', 'title' => 'required|string']);

    $guarded = array_intersect_key($changes, array_flip(['published']));
    if ($guarded) {
        $post->guardrailApprovalFlow = fn () => [
            Flow::make()->anyOfRoles(['editor', 'managing_editor'])->signedBy(1, 'Editorial Approval')->build(),
        ];

        Guardrails::capture($post, $guarded, 'updating');

        return back()->with('status', 'Submitted for approval.');
    }

    $post->update($changes);

    return back()->with('status', 'Updated immediately.');
}
```

**Why it works:** `GuardrailApprovalService::capture()` stores the requested change, creates steps, and emits events. Your controller stays focused on validation and messaging.

---

## Pattern 2 – Build Bespoke Approve/Reject Endpoints

When you want full control over routing and middleware (for example, in a tenant-aware API), write slim controllers that reuse Guardrails models and services.

### Approve

```php
use Illuminate\Http\Request;
use OVAC\Guardrails\Events\ApprovalRequestCompleted;
use OVAC\Guardrails\Events\ApprovalStepApproved;
use OVAC\Guardrails\Models\ApprovalStep;
use OVAC\Guardrails\Support\SigningPolicy;

public function approve(Request $request, int $requestId, int $stepId)
{
    $payload = $request->validate(['comment' => 'nullable|string|max:1000']);
    $user = $request->user(config('guardrails.auth.guard'));

    $step = ApprovalStep::where('request_id', $requestId)->findOrFail($stepId);
    abort_unless($step->status === 'pending', 422, 'Step already finalised.');
    abort_unless(SigningPolicy::canSign($user, (array) ($step->meta['signers'] ?? []), $step), 403);

    $signature = $step->signatures()->updateOrCreate(
        ['signer_id' => $user->id],
        ['decision' => 'approved', 'signed_at' => now(), 'comment' => $payload['comment'] ?? null]
    );

    event(new ApprovalStepApproved($step, $signature));

    $approvedCount = $step->signatures()->where('decision', 'approved')->count();
    if ($approvedCount >= (int) $step->threshold) {
        $step->status = 'completed';
        $step->completed_at = now();
        $step->save();

        $requestModel = $step->request;
        if ($requestModel && !$requestModel->steps()->where('status', 'pending')->exists()) {
            $requestModel->state = 'approved';
            $requestModel->save();

            if ($model = $requestModel->approvable) {
                $apply = function ($instance) use ($requestModel): void {
                    foreach ((array) $requestModel->new_data as $key => $value) {
                        $instance->{$key} = $value;
                    }
                    $instance->save();
                };

                method_exists($model, 'withoutGuardrail')
                    ? $model->withoutGuardrail($apply)
                    : $apply($model);
            }

            event(new ApprovalRequestCompleted($requestModel));
        }
    }

    return response()->json(['status' => 'success']);
}
```

### Reject

```php
use OVAC\Guardrails\Models\ApprovalStep;
use OVAC\Guardrails\Services\GuardrailApprovalService as Guardrails;

public function reject(Request $request, int $requestId, int $stepId)
{
    $payload = $request->validate(['comment' => 'nullable|string|max:1000']);
    $user = $request->user(config('guardrails.auth.guard'));

    $step = ApprovalStep::where('request_id', $requestId)->findOrFail($stepId);
    abort_unless($step->status === 'pending', 422, 'Step already finalised.');

    Guardrails::rejectStep($step, $user, $payload['comment'] ?? null);

    return response()->json(['status' => 'success']);
}
```

Passing `false` as the fourth argument to `rejectStep()` skips the signer check if you have already run a policy.

**When to use this pattern:**
- You need tenant filtering or guardrails must run inside a feature flag boundary.
- Approvals live under namespaced routes (e.g., `/admin/approvals`) with custom rate limits.
- You want to mix traditional controllers with bot webhooks or artisan commands.

---

## Pattern 3 — Extend the Stock Controller

Extending `GuardrailApprovalsController` keeps all built-in behaviour (auth, threshold handling, events) while letting you adjust the presentation. This is ideal for teams with OpenAPI/docblock-driven or contract-first APIs.

```php
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OVAC\Guardrails\Http\Controllers\GuardrailApprovalsController;
use OVAC\Guardrails\Models\ApprovalRequest;
use OVAC\Guardrails\Models\ApprovalSignature;
use OVAC\Guardrails\Models\ApprovalStep;

class ApiApprovalsController extends GuardrailApprovalsController
{
    public function index(Request $request): JsonResponse
    {
        return parent::index($request); // Keep Guardrails filtering + pagination
    }

    public function approveStep(Request $request, int $requestId, int $stepId): JsonResponse
    {
        return parent::approveStep($request, $requestId, $stepId);
    }

    public function rejectStep(Request $request, int $requestId, int $stepId): JsonResponse
    {
        return parent::rejectStep($request, $requestId, $stepId);
    }

    protected function approveStepResponse(Request $request, $user, ApprovalStep $step, ApprovalSignature $signature): JsonResponse
    {
        return response()->json($this->wrap('Step approved successfully.', $this->stepPayload($step->fresh())));
    }

    protected function rejectStepResponse(Request $request, $user, ApprovalStep $step, ApprovalSignature $signature): JsonResponse
    {
        $data = $this->stepPayload($step->fresh());
        $data['step']['rejected'] = true;

        return response()->json($this->wrap('Step rejected successfully.', $data));
    }

    protected function wrap(string $message, array $data = []): array
    {
        return ['status' => 'success', 'success' => true, 'message' => $message, 'data' => $data];
    }

    protected function stepPayload(ApprovalStep $step): array
    {
        $step->loadMissing(['signatures', 'request']);

        return [
            'step' => [
                'id' => $step->id,
                'request_id' => $step->request_id,
                'level' => (int) $step->level,
                'name' => $step->name,
                'status' => strtolower($step->status),
                'threshold' => (int) $step->threshold,
                'completed_at' => optional($step->completed_at)->toIso8601String(),
                'meta' => $step->meta,
                'signatures' => $step->signatures->map(fn ($signature) => [
                    'id' => $signature->id,
                    'signer_id' => $signature->signer_id,
                    'decision' => $signature->decision,
                    'comment' => $signature->comment,
                    'signed_at' => optional($signature->signed_at)->toIso8601String(),
                ])->all(),
            ],
            'request' => $this->requestSummary($step->request),
        ];
    }

    protected function requestSummary(?ApprovalRequest $request): array
    {
        if (!$request) {
            return [];
        }

        return [
            'id' => $request->id,
            'state' => $request->state,
            'initiator_id' => $request->initiator_id,
            'description' => $request->description,
            'created_at' => optional($request->created_at)->toIso8601String(),
            'updated_at' => optional($request->updated_at)->toIso8601String(),
        ];
    }
}
```

### Configurable flows for your custom controllers

Keep your bespoke controllers but still let ops swap steps. `guardrailFlow()` checks `guardrails.flows.<feature>.<action>` first (flat dot key or nested arrays, single-step shorthand allowed), then uses your fallback, and merges meta defaults (e.g., `summary`) onto every step.

```php
use OVAC\Guardrails\Services\Flow;

$flow = $this->guardrailFlow(
    'approvals.custom',
    Flow::make()->anyOfRoles(['ops_manager'])->signedBy(1, 'Ops')->build(),
    ['summary' => 'Custom approval for request #'.$requestModel->id]
);

$result = $this->guardrailIntercept($requestModel, $changes, [
    'description' => 'Custom controller approval.',
    'flow' => $flow,
]);
```

**Why extend instead of reimplement?**
- You inherit every bug fix and feature Guardrails ships.
- The Guardrails controller already filters approvals so users only see steps they can act on.
- You only override the presentation or logging you care about.

> Rejection signatures now include threshold metadata. The default `rejectStepResponse()` returns `rejections.count`, `rejections.required`, and `rejections.maximum` so clients can display progress toward the failure threshold.

### Formatting the Listing Response

Even after extending the controller you can reshape the listing. Override `indexResponse()` to wrap the paginator in your preferred envelope while leaving filtering and security to Guardrails.

```php
use Illuminate\Pagination\LengthAwarePaginator;

protected function indexResponse(LengthAwarePaginator $results, Request $request, $user): JsonResponse
{
    return response()->json([
        'status' => 'success',
        'data' => $results->getCollection()->map(fn (ApprovalRequest $approval) => [
            'id' => $approval->id,
            'state' => $approval->state,
            'description' => $approval->description,
            'initiator_id' => $approval->initiator_id,
            'created_at' => optional($approval->created_at)->toIso8601String(),
        ]),
        'meta' => [
            'current_page' => $results->currentPage(),
            'per_page' => $results->perPage(),
            'total' => $results->total(),
        ],
    ]);
}
```

> ℹ️ You still call `parent::index($request)` from your route action; Guardrails will invoke this override automatically before returning the HTTP response.

---

## Controller Hook Reference (With Typical Customisations)

`GuardrailApprovalsController` exposes protected hooks specifically so you can swap in your own logic. The list below shows the most common overrides and what teams use them for.

### Authentication & Session

| Hook | Default | Customisation Ideas |
| --- | --- | --- |
| `guardName()` | Reads `guardrails.auth.guard` | Point to a different guard for internal APIs (`'internal'`, `'sanctum'`, etc.) |
| `getAuthenticatedUser()` | `request()->user()` | Support API tokens or service accounts when no session exists |
| `unauthenticatedResponse()` | `401` JSON payload | Align error envelope with your API schema |

### Abilities & Policies

| Hook | Default | Use Cases |
| --- | --- | --- |
| `viewAbility()` / `signAbility()` / `rejectAbility()` | Values from config | Map to comprehensive policies such as `can('approvals.sign', $team)` |
| `authorizeIndex()` / `authorizeApprove()` / `authorizeReject()` | `userHasAbility()` | Run bespoke policy checks (department matches, feature flag on) |
| `userHasAbility()` | Delegates to `can()` | Integrate with token abilities or Laravel Permission |

### Listing & Visibility

| Hook | Default Behaviour | Customisations |
| --- | --- | --- |
| `resolvePerPage()` | Cap at 100 | Lower/raise caps per client, support `per_page=all` |
| `newIndexQuery()` | Pending requests with relationships | Join tenant tables, preload extra relations |
| `paginateIndexResults()` | Array pagination with filtering by user relevance | Replace with cursor pagination or GraphQL connection |
| `filterApprovalsForUser()` / `requestRelatesToUser()` / `stepRelatesToUser()` | Show requests the user initiated, signed, or can sign | Expose managerial view or team dashboards |
| `resolveUserIdentifier()` | Uses `getAuthIdentifier()` then `id` | Support UUIDs or external IDs |

### Step Lifecycle & Validation

| Hook | Purpose | Customisations |
| --- | --- | --- |
| `validateApproveStep()` / `validateRejectStep()` | Validate request payloads | Capture justification codes, attachments, or OTP tokens |
| `resolveApprovalStep()` | Locate step by request + id | Add tenant guards or load via slugs |
| `ensureStepIsPending()` / `stepAlreadyFinalizedResponse()` | Handle stale requests | Allow resubmitting with warnings, or auto-redirect |
| `canUserSignStep()` / `ineligibleSignerResponse()` | Enforce signer rules | Combine with HR data, provide actionable error messages |
| `resolveRejectionThresholds()` | Determine min/max rejections | Swap in custom strategies (e.g., unanimous, weighted) |
| `rejectionCount()` | Count current rejection signatures | Reuse cached counts or preloaded relations |
| `shouldFinalizeRejectedStep()` | Decide when to mark the step rejected | Delay finalisation until downstream checks pass |

### Recording Signatures & Events

| Hook | Purpose | Customisations |
| --- | --- | --- |
| `recordApprovalSignature()` / `recordRejectionSignature()` | Persist decisions | Attach metadata (IP address, MFA evidence, ticket IDs) |
| `fireStepApprovedEvent()` / `fireStepRejectedEvent()` | Emit domain events | Dispatch jobs, webhooks, or analytics metrics |
| `finalizeStep()` / `finalizeRejectedStep()` | Transition step state | Queue async work, notify approvers, trigger re-evaluation |
| `completeApprovalRequest()` / `rejectApprovalRequest()` | Update request state | Integrate with change management tools or compliance systems |
| `applyApprovedModelChanges()` / `applyChangesToModel()` | Apply staged data | Mutate payload before persisting, call external services |
| `fireRequestCompletedEvent()` / `fireRequestRejectedEvent()` | Notify listeners | Broadcast to Slack/email, feed incident response workflows |

### Response Shaping

| Hook | Default | Example Override |
| --- | --- | --- |
| `indexResponse()` | `{ success: true, data: paginator }` | Wrap in `{ data, meta }` or transform items before returning |
| `approveStepResponse()` / `rejectStepResponse()` | `{ success: true, approved/rejected: true }` | Return structured envelopes, include fresh step/request payloads |

**Tip:** Whenever you override a hook, call the parent implementation unless you intentionally replace the behaviour. That ensures future Guardrails updates (bug fixes, events, logging) continue to work.

---

## End-to-End Example: Internal API Rollout

A typical enterprise rollout follows these steps:

1. **Capture guarded fields** inside existing resource controllers (Pattern 1) so business logic stays familiar.
2. **Expose internal approvals endpoints** by extending `GuardrailApprovalsController` (Pattern 3) to keep docblocks, rate limits, and consistent success envelopes.
3. **Provide manager-only screens** by overriding `filterApprovalsForUser()` to show team requests.
4. **Broadcast events** in `fireRequestCompletedEvent()` / `fireRequestRejectedEvent()` to notify Slack and update change logs.
5. **Automate rejections** by calling `GuardrailApprovalService::rejectStep()` from background jobs when external validation fails (Pattern 2 helpers).

With these pieces in place, Guardrails becomes an implementation detail—the rest of your codebase keeps its existing conventions while gaining robust approval flows.

---

## Related Guides

- [Controller Interception Guide](./usage-controllers.md)
- [Model Guarding Guide](./usage-models.md)
- [Flow Builder Reference](./flow-builder.md)
- [Auditing & Changelog](./auditing-and-changelog.md)
- [Full Testing Guide](./testing-full.md)
