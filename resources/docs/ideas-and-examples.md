title: Ideas & Examples
description: 10 robust scenarios with copy-paste snippets.

# Ideas & Examples (10)

1) AI Triage for Approvals
```php
Event::listen(\OVAC\Guardrails\Events\ApprovalRequestCaptured::class, function ($e) {
    $score = app('ai')->riskScore($e->request->new_data);
    if ($score > 0.8) {
        // Add an executive step dynamically
        $flow = \OVAC\Guardrails\Services\FlowExtensionBuilder::make()
            ->rolesAny(['ciso'])
            ->toStep(1, 'CISO Review')
            ->build();
        $e->request->meta = array_merge($e->request->meta ?? [], ['extra_flow' => $flow]);
        $e->request->save();
    }
});
```

2) PR Gating (Deploy Flags)
```php
// Treat a PR merge as applying a feature flag update guarded by Ops
Flow::make()->permissionsAny(['ops.deploy'])->toStep(1, 'Ops Gate')->build();
```

3) KYC Review
```php
Flow::make()->rolesAny(['kyc_officer','compliance_analyst'])->toStep(1, 'KYC Check')->build();
```

4) GDPR Delete Requests
```php
Flow::make()->rolesAny(['dpo','security_officer'])->toStep(1, 'Data Deletion Approval')->build();
```

5) Marketing Blast
```php
Flow::make()->rolesAny(['marketing_manager','growth_lead'])->toStep(1, 'Send Approval')->build();
```

6) Vendor Access Grant
```php
Flow::make()->rolesAny(['it_admin','security_officer'])->toStep(1, 'Access Grant')->build();
```

7) Payment Schedule Change
```php
Flow::make()->rolesAny(['finance_manager'])
  ->toStep(1, 'Finance Approval')
  ->build();
```

8) Feature Ramp % Change
```php
Flow::make()->permissionsAny(['ops.change'])
  ->includeInitiator(true, true)
  ->toStep(2, 'Ops Review')
  ->build();
```

9) Schema Migration Toggle
```php
Flow::make()->rolesAny(['sre','eng_lead'])->toStep(2, 'Release Gate')->build();
```

10) External Partner Data Push
```php
Flow::make()->rolesAny(['bd_lead','legal_counsel'])
  ->toStep(2, 'Partner Data Release')
  ->build();
```

Each example can be plugged into model `humanApprovalFlow()` or computed in your controller/interceptor at runtime.

