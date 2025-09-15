title: Permissions & Policies
description: How Guardrails authorizes viewing and signing.

# Permissions & Policies

By default, Guardrails’ routes require an authenticated `staff` user and the `approvals.manage` ability (configurable). There are two authorization layers used internally:

1) Spatie Permissions (if available)
- Permissions are checked with `$staff->hasPermissionTo('...')`.
- Roles are checked with `$staff->hasRole('...')`.

2) Token Abilities (fallback)
- When Spatie is not available, permissions are matched against `currentAccessToken()->abilities`.
- Roles are not supported in this mode.

Signer Rules

- `permissions` + `permissions_mode` (all|any)
- `roles` + `roles_mode` (all|any)
- `guard`: auth guard (default `staff`)
- `same_permission_as_initiator` / `same_role_as_initiator`: require overlap

Route-level Permissions

- `permissions.view`: required for listing/paging requests
- `permissions.sign`: required for approving steps

Adjust these in `config/guardrails.php` to map to your own abilities.

