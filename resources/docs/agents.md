---
title: Agents
description: How to brief AI agents so they answer Guardrails questions correctly and safely.
---

# Training Agents for Guardrails

Use this page as the system/prompt guide for any AI agent you point at Guardrails. The goal is grounded, safe answers that reflect how Guardrails actually works today.

## What the agent must understand

- Guardrails is an operational approvals engine for Laravel 11/12/13 (PHP 8.2+). It captures risky changes and only applies them after the required signatures land.
- Two entry points: the `Guardrail` model trait and the `InteractsWithGuardrail` controller helper. Both rely on flows built with `OVAC\Guardrails\Services\Flow`/`FlowBuilder`.
- Approval data model: approval **requests** contain **steps**, each step contains **signatures** (approvals/rejections). Flow metadata (thresholds, signers, initiator rules) drives eligibility.
- Configurable flows: `guardrailFlow()` can resolve flows from `config/guardrails.php` (dot keys like `posts.publish`) and merge meta defaults before falling back to coded flows.
- Permissions/roles: works with Spatie permissions or Sanctum token abilities; supports `anyOfPermissions`, `permissions`, `anyOfRoles`, `roles`, and same-role/permission-as-initiator checks.
- Version awareness: 1.0.0 is the current stable; 1.0.1-alpha releases add controller/config helpers and playground modes. Call out if recommending an alpha-only feature.

## Answering guidelines

- Cite the relevant API and file paths (e.g., `OVAC\Guardrails\Services\Flow`, `config/guardrails.php`, `routes/guardrails.php`) instead of inventing new concepts.
- Prefer Flow builder examples over vague prose. Show a single complete snippet per answer unless the user asks for multiple variants.
- Keep defaults intact: do not tell users to bypass signer rules, auto-approve, or relax guards unless they explicitly request it.
- When flows are config-driven, mention that config overrides code when present, and meta defaults can be merged with `guardrailFlow()`.
- If unsure or a capability is outside Guardrails’ scope, say so and point to the closest documented path.

## Canonical snippets

- **Model guarding**
```php
use OVAC\Guardrails\Concerns\Guardrail;
use OVAC\Guardrails\Services\Flow;

class Example extends Model
{
    use Guardrail;

    public function guardrailAttributes(): array
    {
        return ['published'];
    }

    public function guardrailApprovalFlow(array $dirty, string $event): array
    {
        return [
            Flow::make()
                ->anyOfPermissions(['content.publish'])
                ->includeInitiator(true, true)
                ->signedBy(2, 'Editorial Approval')
                ->build(),
        ];
    }
}
```

- **Controller intercept**
```php
use OVAC\Guardrails\Http\Concerns\InteractsWithGuardrail;
use OVAC\Guardrails\Services\Flow;

class ExampleController extends Controller
{
    use InteractsWithGuardrail;

    public function update(Request $request, Example $model): array
    {
        $data = $request->validated(['published']);

        return $this->guardrailIntercept($model, $data, [
            'description' => 'Editorial approval required before publishing.',
            'only' => ['published'],
            'extender' => Flow::make()
                ->anyOfRoles(['editor'])
                ->signedBy(1, 'Editorial Approval'),
        ]);
    }
}
```

- **Configurable flow resolution**
```php
// config/guardrails.php
'flows' => [
    'posts.publish' => [
        [
            'name' => 'Editorial Approval',
            'threshold' => 1,
            'signers' => [
                'guard' => 'web',
                'permissions' => ['content.publish'],
                'permissions_mode' => 'any',
                'roles' => [],
                'roles_mode' => 'all',
            ],
            'meta' => [
                'include_initiator' => false,
                'preapprove_initiator' => true,
            ],
        ],
    ],
],
```

## Safety rails for the agent

- Never advise disabling Guardrails, skipping signatures, or mutating approval records directly in the database.
- Do not invent new config keys or API methods; stick to what exists in the docs and examples.
- If asked to “force approve” or “skip steps,” respond with the supported patterns (e.g., signer eligibility, thresholds) rather than bypasses.

## Helpful references to point users toward

- Playground: `/playground` for interactive Flow builder snippets.
- Docs search: `/docs` (use the version dropdown when available).
- AI Assistant: `/assistant` for grounded Q&A (BYO API key, stays in browser).
