title: Flow Builder Reference
description: All methods on FlowExtensionBuilder with examples.

# Flow Builder Reference

Namespace: `OVAC\\Guardrails\\Services\\FlowExtensionBuilder`
Implements: `OVAC\\Guardrails\\Contracts\\FlowExtender`

Usage

```php
use OVAC\\Guardrails\\Services\\FlowExtensionBuilder as Flow;

$flow = Flow::make()
  ->permissionsAny(['orders.manage','orders.escalate'])
  ->includeInitiator(true, true)
  ->toStep(2, 'Ops Review')
  ->rolesAny(['finance_manager','ops_manager'])
  ->toStep(1, 'Management')
  ->build();
```

API

- `static make(): static` — Create a new builder.
- `guard(string $guard): static` — Auth guard for signer checks (default `staff`).
- `permissions(array|string $perms): static` — Append permission(s); all-of by default.
- `setPermissions(array|string $perms): static` — Replace permissions list.
- `permissionsAny(array|string $perms): static` — Use any-of semantics.
- `requireAnyPermissions(): static` — Alias to set any-of mode.
- `requireAllPermissions(): static` — Alias to set all-of mode.
- `roles(array|string $roles): static` — Append role(s); all-of by default.
- `setRoles(array|string $roles): static` — Replace roles list.
- `rolesAny(array|string $roles): static` — Use any-of semantics.
- `requireAnyRoles(): static` — Alias to set any-of mode.
- `requireAllRoles(): static` — Alias to set all-of mode.
- `includeInitiator(bool $include = true, bool $preapprove = true): static` — Include initiator as a potential signer and optionally pre-approve.
- `samePermissionAsInitiator(bool $require = true): static` — Require overlap with initiator’s permission(s).
- `sameRoleAsInitiator(bool $require = true): static` — Require overlap with initiator’s role(s).
- `toStep(?int $threshold = 1, ?string $name = null, array $meta = []): static` — Finalize current step and push to flow.
- `addStep(array $step): static` — Add a normalized step array directly.
- `build(): array` — Return the configured flow. If only signers were configured, creates a single step.

Notes

- Any-of vs all-of determines whether a signer needs one or all listed permissions/roles.
- Pre-approving initiator counts them immediately toward the threshold.
- “Same-as-initiator” constraints require Spatie permissions/roles to compute overlaps.

