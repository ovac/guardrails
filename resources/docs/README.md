title: Documentation Index
description: Complete Guardrails documentation with guides and references.

# Guardrails Documentation

Welcome to the Guardrails docs. This guide covers installation, configuration, how to guard models or intercept controllers, how to design approval flows, and how the database and UI fit together. Each section includes practical, copy‑paste examples.

Sections

- [Getting Started](./getting-started.md)
- [Concepts & Overview](./overview.md)
- [Configuration Reference](./configuration.md)
- [Model Guarding Guide](./usage-models.md)
- [Controller Interception Guide](./usage-controllers.md)
- [Flow Builder Reference](./flow-builder.md)
- [Common Patterns](./patterns.md)
- [Use Cases](./use-cases.md)
- [Organization Playbook](./organization-playbook.md)
- [Advanced Flows](./advanced.md)
- [Voting Models](./voting-models.md)
- [Bots & Automation](./bots-and-automation.md)
- [Auditing & Changelog](./auditing-and-changelog.md)
- [Config Recipes](./config-recipes.md)
- [Custom Controllers](./custom-controllers.md)
- [External Document Signing](./external-signing.md)
- [Email & SMS Verification](./verification-examples.md)
- [Ideas & Examples](./ideas-and-examples.md)
- [Extending Models & Migrations](./extending-models-and-migrations.md)

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

- [Database & Migrations](./database.md)
- [API Reference](./api.md)
- [UI & Assets](./ui.md)
- [Permissions & Policies](./permissions.md)
- [Testing & Local Dev](./testing.md)
- [FAQ](./faq.md)