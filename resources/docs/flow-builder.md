title: Flow Builder Reference
description: All methods on FlowBuilder with examples.

# Flow Builder Reference

Namespace: `OVAC\\Guardrails\\Services\\FlowBuilder`
Implements: `OVAC\\Guardrails\\Contracts\\FlowExtender`

Usage

```php
use OVAC\\Guardrails\\Services\\Flow;

$flow = Flow::make()
  ->anyOfPermissions(['orders.manage','orders.escalate'])
  ->includeInitiator(true, true)
  ->signedBy(2, 'Ops Review')
  ->anyOfRoles(['finance_manager','ops_manager'])
  ->signedBy(1, 'Management')
  ->build();
```

API

- `static make(): static` — Create a new builder.
- `guard(string $guard): static` — Auth guard for signer checks (defaults to your configured guard, typically `web`).
- `permissions(array|string $perms): static` — Append permission(s); all-of by default.
- `setPermissions(array|string $perms): static` — Replace permissions list.
- `anyOfPermissions(array|string $perms): static` — Use any-of semantics.
- `requireAnyPermissions(): static` — Alias to set any-of mode.
- `requireAllPermissions(): static` — Alias to set all-of mode.
- `roles(array|string $roles): static` — Append role(s); all-of by default.
- `setRoles(array|string $roles): static` — Replace roles list.
- `anyOfRoles(array|string $roles): static` — Use any-of semantics.
- `requireAnyRoles(): static` — Alias to set any-of mode.
- `requireAllRoles(): static` — Alias to set all-of mode.
- `includeInitiator(bool $include = true, bool $preapprove = true): static` — Include initiator as a potential signer and optionally pre-approve.
- `samePermissionAsInitiator(bool $require = true): static` — Require overlap with initiator’s permission(s).
- `sameRoleAsInitiator(bool $require = true): static` — Require overlap with initiator’s role(s).
- `rejectionThreshold(?int $min, ?int $max = null): static` — Set minimum/maximum rejection votes required to fail the step (defaults to a simple majority of the approval threshold).
- `minRejections(int $min): static` — Convenience alias for `rejectionThreshold($min, current max)`.
- `maxRejections(?int $max): static` — Convenience alias for `rejectionThreshold(current min, $max)`.
- `signedBy(?int $threshold = 1, ?string $name = null, array $meta = []): static` — Finalize current step and push to flow.
- `addStep(array $step): static` — Add a normalized step array directly.
- `build(): array` — Return the configured flow. If only signers were configured, creates a single step.

Notes

- Any-of vs all-of determines whether a signer needs one or all listed permissions/roles.
- Pre-approving initiator counts them immediately toward the threshold.
- If you do not set a rejection threshold, Guardrails requires a simple majority of the approval threshold (e.g., approval threshold `3` ⇒ `2` rejections to fail).
- “Same-as-initiator” constraints require Spatie permissions/roles to compute overlaps.
- `Flow::make()` automatically seeds the guard with `guardrails.auth.guard` (falling back to `auth.defaults.guard`), so flows stay aligned with your configuration.
