title: Signing Policy Reference
description: How Guardrails evaluates permissions, roles, and custom resolvers.

tags:
  - approvals
  - signing
  - permissions
  - roles

# Signing Policy Reference

Guardrails relies on a signing policy to decide whether an authenticated initiator can approve a step. The evaluation order is predictable so you can design flows with confidence.

## Permission checks

1. **Permissions come first.** The signer rules list `permissions` plus a `permissions_mode` (`all` or `any`).
2. Guardrails looks for permission helpers on the user:
   - If the user exposes Spatie-style methods (`hasPermissionTo`), those are used.
   - Otherwise, Guardrails inspects token abilities (e.g. `currentAccessToken()->abilities`).
3. For `all`, the initiator must satisfy every listed permission. For `any`, a single match is enough.

Permissions work out of the box—no extra configuration required.

## Role checks

Roles are optional. If you include them in a step:

- With Spatie roles available (`hasRole`), Guardrails uses the package directly.
- Without Spatie, Guardrails falls back to a resolver:
  - By default, it inspects common patterns (an Eloquent relationship or `roles` attribute).
  - You can take full control via the `guardrails.signing.resolve_roles_using` config key. Set it to a closure that receives the authenticated user and returns an array of role identifiers.

```php
// config/guardrails.php
'signing' => [
    'resolve_roles_using' => static function ($user) {
        return $user->teams->pluck('slug')->all();
    },
],
```

## Initiator overlap

Flows can demand overlap with the initiator using `same_permission_as_initiator` or `same_role_as_initiator`. Guardrails compares the initiator’s permissions or roles against the signer’s set after running the checks above. If no overlap exists, the signer is rejected.

## Tokens and abilities

When using Laravel Sanctum or similar token guards, Guardrails will treat token abilities as permissions. Make sure the token includes the required ability names.

> Guardrails uses the configured auth guard (default: `auth.defaults.guard`). You can change it via `GUARDRAILS_AUTH_GUARD` or `config/guardrails.php`. The helper `OVAC\Guardrails\Support\Auth` exposes `guardName()`, `guard()`, and `user()` if you need to mirror Guardrails’ guard selection in custom code.

## Guard selection & per-step overrides

- `Flow::make()` seeds the guard from `config('guardrails.auth.guard')`, falling back to your app’s default guard. This keeps flows aligned with how you authenticate reviewers.
- You can override the guard for a specific step by chaining `guard('other-guard')` before calling `signedBy()`. See the [Controller Interception Guide](./usage-controllers.md#mixing-guards-in-a-flow) for a full example.

## Troubleshooting

- **Unexpected denials?** Log the signer meta to confirm the permissions/roles being evaluated.
- **Custom guards?** Double-check the resolver returns plain strings; nested arrays or objects will be ignored.
- **Mixed ecosystems?** You can use both permissions and roles in the same step; Guardrails requires that both pass according to their respective modes.

## Related configuration

- `guardrails.permissions.view` and `guardrails.permissions.sign` gate the built-in API routes.
- `guardrails.signing.resolve_roles_using` supplies a custom resolver.

For examples of defining signer rules, see the [Model Guarding Guide](./usage-models.md) and [Controller Interception Guide](./usage-controllers.md).
