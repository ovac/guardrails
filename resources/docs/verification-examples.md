title: Email & SMS Verification Approvals
description: Simple copy‑paste examples to approve steps via email links or SMS codes.

# Email & SMS Verification Approvals

These patterns show how to use common verification channels to approve a Guardrails step without building a full UI.

## Email Link Approval

Send a signed URL to the approver’s email; clicking the link records the signature.

Routes:

```php
// web.php
Route::get('/approvals/{request}/{step}/email-approve', [ApproveByEmailController::class, 'approve'])
    ->name('approvals.email.approve')
    ->middleware(['signed']); // Laravel signed URLs
```

Controller:

```php
use Illuminate\Http\Request;
use OVAC\Guardrails\Models\ApprovalRequest;
use OVAC\Guardrails\Models\ApprovalStep;
use OVAC\Guardrails\Models\ApprovalSignature;
use OVAC\Guardrails\Support\SigningPolicy;

class ApproveByEmailController
{
    public function sendInvite(ApprovalStep $step, \App\Models\User $user)
    {
        // Generate a temporary signed URL for the specific user
        $url = URL::temporarySignedRoute('approvals.email.approve', now()->addHours(24), [
            'request' => $step->request_id,
            'step' => $step->id,
            'uid' => $user->getKey(),
        ]);

        Mail::to($user->email)->send(new \App\Mail\ApprovalInvite($url));
    }

    public function approve(Request $request, int $requestId, int $stepId)
    {
        $userId = (int) $request->query('uid');
        $user = \OVAC\Guardrails\Support\Auth::findUserById($userId);

        $step = ApprovalStep::where('request_id', $requestId)->findOrFail($stepId);
        abort_unless(SigningPolicy::canSign($user, (array) ($step->meta['signers'] ?? []), $step), 403);

        ApprovalSignature::updateOrCreate(
            ['step_id' => $step->id, 'signer_id' => $user->id],
            ['decision' => 'approved', 'signed_at' => now(), 'comment' => 'Email link']
        );

        return redirect()->route('guardrails.api.index')->with('status', 'Approved via email');
    }
}
```

## SMS OTP Approval

Send a short code via SMS; the approver enters it on a simple form to approve.

Routes:

```php
Route::get('/approvals/{request}/{step}/sms', [SmsApproveController::class, 'form'])->name('approvals.sms.form');
Route::post('/approvals/{request}/{step}/sms', [SmsApproveController::class, 'verify'])->name('approvals.sms.verify');
```

Controller:

```php
use OVAC\Guardrails\Models\ApprovalRequest;
use OVAC\Guardrails\Models\ApprovalStep;
use OVAC\Guardrails\Models\ApprovalSignature;
use OVAC\Guardrails\Support\SigningPolicy;

class SmsApproveController
{
    public function sendCode(ApprovalStep $step, \App\Models\User $user)
    {
        $code = random_int(100000, 999999);
        // Store code in signature meta (create a pending row)
        $sig = ApprovalSignature::firstOrCreate(['step_id' => $step->id, 'signer_id' => $user->id]);
        $meta = $sig->meta ?? [];
        $meta['otp'] = ['code' => (string) $code, 'expires_at' => now()->addMinutes(10)->toISOString()];
        $sig->meta = $meta; $sig->save();

        // Send via your SMS provider
        app('sms')->send($user->phone, "Approval code: $code");
    }

    public function form(Request $request, int $requestId, int $stepId)
    {
        return view('approvals.sms', compact('requestId','stepId'));
    }

    public function verify(Request $request, int $requestId, int $stepId)
    {
        $request->validate(['user_id' => 'required|integer', 'code' => 'required|string']);
        $user = \OVAC\Guardrails\Support\Auth::findUserById($request->integer('user_id'));
        $step = ApprovalStep::where('request_id', $requestId)->findOrFail($stepId);

        abort_unless(SigningPolicy::canSign($user, (array) ($step->meta['signers'] ?? []), $step), 403);

        $sig = ApprovalSignature::firstOrCreate(['step_id' => $step->id, 'signer_id' => $user->id]);
        $meta = $sig->meta ?? [];
        $otp = (array) ($meta['otp'] ?? []);
        abort_unless(!empty($otp) && hash_equals((string) $otp['code'], (string) $request->string('code')), 422, 'Invalid code');
        abort_unless(now()->lte(\Illuminate\Support\Carbon::parse($otp['expires_at'])), 422, 'Code expired');

        $sig->decision = 'approved';
        $sig->signed_at = now();
        $sig->comment = 'SMS code';
        $sig->save();

        return redirect()->route('guardrails.api.index')->with('status', 'Approved via SMS');
    }
}
```

These verification patterns can coexist with role/permission rules: the policy still ensures only eligible users can approve.
