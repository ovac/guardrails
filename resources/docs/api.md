---
title: API Reference
description: Endpoints for listing, approving, and rejecting requests.
tags: [api, http]
---

# API Reference

## GET /\{route_prefix\}

List pending approval requests visible to the authenticated user. Default prefix: `guardrails/api`.

Notes:
- Results are filtered server-side so users only see requests they initiated, have already signed, or are eligible to sign.
- Each `steps` entry now includes signer metadata so UIs can show who qualifies:
  - `permissions_required` — array of permission strings configured for the step.
  - `permissions_mode` — `all` or `any` matching the flow configuration.
  - `roles_required` — array of role slugs that satisfy the step.
  - `roles_mode` — `all` or `any` for the associated roles.

Query params:
- `per_page`: integer, default 25, max 100.

## POST /\{route_prefix\}/{request}/steps/{step}/approve

Approve a step for the current authenticated user on the configured guard.

Body params:
- `comment`: optional string (max 1000).

Auth & Policy:
- Requires `auth:{guard}` where `{guard}` defaults to `auth.defaults.guard` (usually `web`) and the `guardrails.permissions.sign` ability. The user must satisfy the step’s signer policy (permissions/roles).

## POST /\{route_prefix\}/{request}/steps/{step}/reject

Record a rejection for the current step. Guardrails keeps the step pending until the configured rejection threshold is met (defaults to a simple majority of the approval threshold). Once the threshold is satisfied the step and request transition to `rejected`.

Body params:
- `comment`: optional string (max 1000) recorded on the rejection signature.

Auth & Policy:
- Same guard and ability requirements as the approve endpoint. The authenticated user must satisfy the signer policy for the step in order to reject it.

Response body includes:
- `rejected` — Boolean indicating whether the step transitioned to the rejected state after this signature.
- `rejections.count` — Rejections recorded so far.
- `rejections.required` — Minimum rejections required to fail the step (defaults to majority if not explicitly set in the flow builder).
- `rejections.maximum` — Optional hard-stop when configured via the flow builder.
