---
title: Guardrails Overview
---

# Guardrails

Guardrails adds actor approval flows to critical mutations in your management system. Define guarded attributes, build multi‑step signer policies, and expose a simple API + UI for reviewers to sign.

## Endpoints

- GET `/{route_prefix}` — List pending requests
- POST `/{route_prefix}/{request}/steps/{step}/approve` — Approve a step

## Usage Patterns

- Model trait: `OVAC\\Guardrails\\Concerns\\ActorGuarded`
- Controller helper: `InteractsWithActorApproval`
- Flow builder: `OVAC\\Guardrails\\Services\\FlowExtensionBuilder`

## Configuration

See `config/guardrails.php` for route + policy settings.
