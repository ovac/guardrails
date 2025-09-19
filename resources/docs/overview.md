---
title: Guardrails Overview
---

# Guardrails

Guardrails is the approvals layer for the parts of your product that need humans in the loop. Flag the attributes that require review, describe who must sign (and in what order), and Guardrails captures the change, tracks signatures, and applies the update when every step is satisfied.

## Endpoints

- GET `/{route_prefix}` — List pending requests
- POST `/{route_prefix}/{request}/steps/{step}/approve` — Approve a step

## Usage Patterns

- Model trait: `OVAC\\Guardrails\\Concerns\\Guardrail`
- Controller helper: `OVAC\\Guardrails\\Http\\Concerns\\InteractsWithGuardrail`
- Flow builder: `OVAC\\Guardrails\\Services\\FlowBuilder`

## Configuration

See `config/guardrails.php` for route + policy settings.

## Continue Exploring

- [Model Guarding Guide](./usage-models.md) — Attach Guardrails directly to Eloquent models.
- [Controller Interception Guide](./usage-controllers.md) — Route risky requests through approvals.
- [Advanced Flows](./advanced.md) — Build dynamic and context-aware signing policies.
- [Common Patterns](./patterns.md) — Start from ready-made approval recipes.
- [Full Testing Guide](./testing-full.md) — Learn how to exercise Guardrails in your test suite.
