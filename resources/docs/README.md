title: Documentation Index
description: Complete Guardrails documentation with guides and references.

# Guardrails Documentation

Welcome to the Guardrails manual. Inside you’ll find everything you need to wire guarded attributes, design approval flows, expose the reviewer experience, and plug Guardrails into the rest of your Laravel stack – complete with practical copy‑paste examples.

Sections

- [Getting Started](./getting-started.md)
- [Concepts & Overview](./overview.md)
- [Configuration Reference](./configuration.md)
- [Model Guarding Guide](./usage-models.md)
- [Controller Interception Guide](./usage-controllers.md)
- [Flow Builder Reference](./flow-builder.md)
- [Signing Policy Reference](./signing-policy.md)
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
flowchart TD
  %% 1. Interception
  A["Mutation Attempt<br/>Model (Guardrail trait) or ControllerInterceptor"] --> B{Authenticated via<br/>Guardrails guard?}
  B -- No --> IM["Apply change immediately"]
  B -- Yes --> C["Collect dirty attributes<br/>and Guardrails context"]
  C --> D{Guardable attributes<br/>after only/except?}
  D -- No --> IM
  D -- Yes --> E{Model requires approval?}
  E -- No --> IM
  E -- Yes --> F["GuardrailApprovalService::capture()"]

  %% 2. Capture & Storage
  F --> G["Create ApprovalRequest<br/>with description/context"]
  G --> H["Build steps from model flow<br/>or FlowBuilder override"]
  H --> J["Persist guardrail_approval_steps<br/>and signer meta"]
  J --> K{Initiator included<br/>and eligible?}
  K -- Yes --> L["Auto-create approval signature"]
  K -- No --> M["Await reviewers"]
  L --> M
  M --> N["Event: ApprovalRequestCaptured"]

  %% 3. Reviewer Experience
  N --> O["Approvals API index<br/>filters requestRelatesToUser()"]
  O --> P["Reviewer opens pending step"]
  P --> Q{"SigningPolicy::canSign?"}
  Q -- No --> R1[Return 403 - Ineligible signer]
  Q -- Yes --> S{Decision}

  %% Approve Path
  S -- Approve --> T["Record approval signature<br/>in guardrail_approval_signatures"]
  T --> U["Event: ApprovalStepApproved"]
  U --> V{Approval threshold met?}
  V -- No --> O
  V -- Yes --> W[Mark step completed]
  W --> X{"More steps pending?"}
  X -- Yes --> O
  X -- No --> Y["Mark request state = approved"]
  Y --> Z["Apply new_data to model<br/>via withoutGuardrail()"]
  Z --> ZA["Event: ApprovalRequestCompleted"]

  %% Reject Path
  S -- Reject --> BB["Record rejection signature"]
  BB --> BC["Event: ApprovalStepRejected"]
  BC --> BD{Rejection threshold met?}
  BD -- No --> O
  BD -- Yes --> BE[Mark step rejected]
  BE --> BF[Mark request state = rejected]
  BF --> BG["Event: ApprovalRequestRejected"]

  %% Return path
  R1 -.-> O
```

- [Database & Migrations](./database.md)
- [API Reference](./api.md)
- [UI & Assets](./ui.md)
- [Permissions & Policies](./permissions.md)
- [Testing & Local Dev](./testing.md)
- [FAQ](./faq.md)

Key signals you can listen to:

- `ApprovalRequestCaptured` when a request is created.
- `ApprovalStepApproved` for every approval signature that gets recorded.
- `ApprovalRequestCompleted` once the approval threshold is met and changes are applied.
- `ApprovalStepRejected` for every rejection signature that gets recorded (check `step->status` for pending vs rejected).
- `ApprovalRequestRejected` once the rejection threshold is met and the request is halted.
