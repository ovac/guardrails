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
  A["Mutation attempt<br>(Model trait or ControllerInterceptor)"] --> B{"Guardrails active<br/> guardable attrs<br/> requires approval?"}
  B -- No --> IM["Apply change immediately"]
  B -- Yes --> CAP["Capture approval request"]

  CAP --> PS["Persist steps and signer meta"]
  PS --> INI{"Initiator eligible?"}
  INI -- Yes --> SIG["Auto-create initiator signature"]
  INI -- No --> QUE["Queue for reviewers"]
  SIG --> QUE

  QUE --> E1["Event: ApprovalRequestCaptured"]
  E1 --> IDX["Review queue shows pending steps to eligible users"]
  IDX --> OPEN["Reviewer opens step"]
  OPEN --> CAN{"SigningPolicy allows signer?"}
  CAN -- No --> IDX
  CAN -- Yes --> DEC{Decision}

  DEC -- Approve --> APPR["Record approval signature"]
  APPR --> THR{"Approval threshold met?"}
  THR -- No --> IDX
  THR -- Yes --> STEP_OK["Mark step complete"]
  STEP_OK --> MORE{"More steps pending?"}
  MORE -- Yes --> NEXT["Move to next pending step"]
  NEXT --> IDX
  MORE -- No --> REQ_OK["Mark request approved"]
  REQ_OK --> APPLY["Apply new_data via withoutGuardrail()"]
  APPLY --> END_OK["Event: ApprovalRequestCompleted"]

  DEC -- Reject --> REJ["Record rejection signature"]
  REJ --> RTHR{"Rejection threshold met?"}
  RTHR -- No --> IDX
  RTHR -- Yes --> REQ_REJ["Mark request rejected"]
  REQ_REJ --> END_REJ["Event: ApprovalRequestRejected"]
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
