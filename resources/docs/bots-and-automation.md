title: Bots & Automation
description: Using service accounts, webhooks, and chatops with Guardrails.

# Bots & Automation

Bots can open approval requests (as initiators) or receive events to notify teams. Guardrails stores the `actor_staff_id`, so consider creating a service account user for your bot.

## Service Accounts as Initiators

Use a dedicated `staff` user (e.g., `automation@yourco`) and authenticate via a token with scoped abilities. Changes captured while authenticated as this user will record them as the initiator.

## Event-Driven Integrations

Guardrails fires domain events you can listen to:

- `OVAC\\Guardrails\\Events\\ApprovalRequestCaptured`
- `OVAC\\Guardrails\\Events\\ApprovalStepApproved`
- `OVAC\\Guardrails\\Events\\ApprovalRequestCompleted`

### Example: Send Slack notifications

```php
use Illuminate\\Support\\Facades\\Notification;
use App\\Notifications\\SlackApprovalPing;

Event::listen(\OVAC\Guardrails\Events\ApprovalRequestCaptured::class, function ($e) {
    Notification::route('slack', env('SLACK_WEBHOOK'))
        ->notify(new SlackApprovalPing('New approval request #'.$e->request->id));
});

Event::listen(\OVAC\Guardrails\Events\ApprovalRequestCompleted::class, function ($e) {
    Notification::route('slack', env('SLACK_WEBHOOK'))
        ->notify(new SlackApprovalPing('Request #'.$e->request->id.' approved.'));
});
```

## ChatOps Approvals

- Expose a small endpoint your bot can call to POST the `approve` action with a signed token.
- Or map bot identities to staff users and let them sign steps if they satisfy signer rules.

## CI/CD & Commits

Guardrails can gate config toggles or deploy flags via your admin app:

- Approve a feature flag before a pipeline proceeds.
- Store the approval request ID in your release notes or build metadata.

