---
title: API Reference
description: Endpoints for listing and approving requests.
tags: [api, http]
---

# API Reference

## GET /\{route_prefix\}

List pending approval requests. Default prefix: `staff/v1/guardrails`.

Query params:
- `per_page`: integer, default 25, max 100.

## POST /\{route_prefix\}/{request}/steps/{step}/approve

Approve a step for the current staff user.

Body params:
- `comment`: optional string (max 1000).

Auth & Policy:
- Requires `auth:staff` and `approvals.manage` by default (configurable).

