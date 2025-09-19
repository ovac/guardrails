title: External Document Signing
description: Integrate DocuSeal/DocuSign-style signature flows with Guardrails.

# External Document Signing

Some businesses require a paper trail with legally-binding signatures. This guide shows how to integrate a document provider and wire signatures back to Guardrails.

## Flow Overview

1) On `ApprovalRequestCaptured`, create a document (PDF/HTML) containing a summary of the change and the approver(s).
2) Send the document to the signer(s) via provider API and store the envelope/packet ID on the request meta.
3) Expose a webhook endpoint for the provider to call on sign/decline.
4) When signed, approve the corresponding Guardrails step. On decline, mark the request rejected or leave pending for re-review.

## Step 1: Listen to the Capture Event

```php
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

Event::listen(\OVAC\Guardrails\Events\ApprovalRequestCaptured::class, function ($e) {
    $req = $e->request;

    // Compose a summary document payload
    $doc = [
        'title' => 'Approval Request #'.$req->id,
        'summary' => [
            'approvable' => [$req->approvable_type, $req->approvable_id],
            'changes' => $req->new_data,
            'original' => $req->original_data,
        ],
    ];

    // Example: DocuSeal-like API
    $resp = Http::withToken(config('services.docuseal.token'))
        ->post('https://api.docuseal.co/envelopes', [
            'document' => $doc,
            'signers' => [/* emails, names, roles */],
            'webhook_url' => route('signing.webhook'),
            'metadata' => ['guardrails_request_id' => $req->id],
        ])->json();

    $req->meta = array_merge($req->meta ?? [], [
        'doc_provider' => 'docuseal',
        'doc_envelope_id' => $resp['id'] ?? null,
    ]);
    $req->save();
});
```

## Step 2: Webhook Endpoint

```php
Route::post('/webhooks/doc-signing', function (Illuminate\Http\Request $request) {
    // Verify signature/secret per provider docs
    abort_unless(hash_equals($request->header('X-Signature'), hash_hmac('sha256', $request->getContent(), config('services.docuseal.secret'))), 401);

    $payload = $request->all();
    $guardrailsId = data_get($payload, 'metadata.guardrails_request_id');
    $status = data_get($payload, 'status'); // signed|declined

    $req = \OVAC\Guardrails\Models\ApprovalRequest::findOrFail($guardrailsId);
    $step = $req->steps()->where('status', 'pending')->orderBy('level')->firstOrFail();

    if ($status === 'signed') {
        // Approve on behalf of the signer; map provider identity to your user
        $user = App\Models\User::where('email', data_get($payload, 'signer.email'))->firstOrFail();
        \OVAC\Guardrails\Models\ApprovalSignature::updateOrCreate(
            ['step_id' => $step->id, 'signer_id' => $user->id],
            ['decision' => 'approved', 'signed_at' => now(), 'comment' => 'Doc signed']
        );
    } else {
        // Optionally store a decline note or set state
        $req->state = 'pending';
        $req->save();
    }

    return response()->json(['ok' => true]);
})->name('signing.webhook');
```

This two-part integration lets the provider manage the legal signature while Guardrails manages the approval logic and data application.
