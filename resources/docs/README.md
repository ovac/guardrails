title: Documentation Index
description: Complete Guardrails documentation with guides and references.

# Guardrails Documentation

Welcome to the Guardrails docs. This guide covers installation, configuration, how to guard models or intercept controllers, how to design approval flows, and how the database and UI fit together. Each section includes practical, copy‑paste examples.

Sections

- [Getting Started](resources/docs/getting-started.md)
- [Concepts & Overview](resources/docs/overview.md)
- [Configuration Reference](resources/docs/configuration.md)
- [Model Guarding Guide](resources/docs/usage-models.md)
- [Controller Interception Guide](resources/docs/usage-controllers.md)
- [Flow Builder Reference](resources/docs/flow-builder.md)
- [Common Patterns](resources/docs/patterns.md)
- [Use Cases](resources/docs/use-cases.md)
- [Organization Playbook](resources/docs/organization-playbook.md)
- [Advanced Flows](resources/docs/advanced.md)
- [Voting Models](resources/docs/voting-models.md)
- [Bots & Automation](resources/docs/bots-and-automation.md)
- [Auditing & Changelog](resources/docs/auditing-and-changelog.md)
- [Config Recipes](resources/docs/config-recipes.md)
- [Custom Controllers](resources/docs/custom-controllers.md)
- [External Document Signing](resources/docs/external-signing.md)
- [Email & SMS Verification](resources/docs/verification-examples.md)
- [Ideas & Examples](resources/docs/ideas-and-examples.md)
- [Extending Models & Migrations](resources/docs/extending-models-and-migrations.md)

## How It Works

```mermaid
sequenceDiagram
  participant App as Your App
  participant GR as Guardrails
  participant DB as DB

  App->>GR: Update model or call intercept()
  GR->>App: Identify guarded attributes
  GR->>DB: Insert approval_request + steps
  GR-->>App: Return captured=true
  App->>User: Show pending approvals UI
  User->>GR: Approve step
  GR->>DB: Record signature; check threshold
  GR->>DB: Apply change when last step completes
  GR-->>App: Emit events (captured/approved/completed)
```
- Database & Migrations: resources/docs/database.md
- API Reference: resources/docs/api.md
- UI & Assets: resources/docs/ui.md
- Permissions & Policies: resources/docs/permissions.md
- Testing & Local Dev: resources/docs/testing.md
- FAQ: resources/docs/faq.md
