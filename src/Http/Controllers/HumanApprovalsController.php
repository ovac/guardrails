<?php

namespace OVAC\Guardrails\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use OVAC\Guardrails\Models\ApprovalStep;
use OVAC\Guardrails\Support\SigningPolicy;

/**
 * @group Guardrails
 */
class HumanApprovalsController extends Controller
{
    /**
     * List Pending Requests
     *
     * Details:
     * - Returns approval requests in `pending` state with steps and signatures.
     * - Paginates using `per_page` with max of 100.
     *
     * > Roles or Permissions Required: `approvals.manage`
     *
     * @authenticated
     *
     * @queryParam per_page integer
     * Number of items per page. Default: 25. Example: 50
     */
    public function index(Request $request)
    {
        // Validate pagination input and bound upper limit
        $perPage = min((int) $request->query('per_page', 25), 100);

        $q = \OVAC\Guardrails\Models\ApprovalRequest::query()
            ->with(['approvable','steps.signatures'])
            ->where('state', 'pending')
            ->latest('id');

        // Paginate and return JSON payload for UI/API
        $data = $q->paginate($perPage);
        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Approve Step
     *
     * Details:
     * - Records an approval signature for the current staff member.
     * - When threshold is met, completes the step and the request if last step.
     *
     * > Roles or Permissions Required: `approvals.manage`
     *
     * @authenticated
     *
     * @pathParam request integer required
     * The approval request id. Example: 44
     *
     * @pathParam step integer required
     * The step id belonging to the request. Example: 3
     *
     * @bodyParam comment string
     * Optional reason/comment included with the approval. Example: Looks good.
     */
    public function approveStep(Request $request, int $requestId, int $stepId)
    {
        // Validate request inputs and identify current staff and step
        $request->validate(['comment' => 'nullable|string|max:1000']);
        $guard = \OVAC\Guardrails\Support\Auth::guardName();
        $staff = $request->user($guard);
        $step = ApprovalStep::where('request_id', $requestId)->findOrFail($stepId);

        if ($step->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'Step already finalized.'], 422);
        }

        // Enforce signer policy, including any-of/all-of and initiator overlap
        $signers = (array) ($step->meta['signers'] ?? []);
        if (!SigningPolicy::canSign($staff, $signers, $step)) {
            return response()->json(['success' => false, 'message' => 'You are not eligible to sign this step.'], 403);
        }

        // Record or update an approval signature for the current staff
        \OVAC\Guardrails\Models\ApprovalSignature::updateOrCreate(
            ['step_id' => $step->id, 'staff_id' => $staff->id],
            ['decision' => 'approved', 'signed_at' => now(), 'comment' => $request->string('comment')]
        );

        // Emit event for auditing/integrations
        $signature = $step->signatures()->where('staff_id', $staff->id)->latest('id')->first();
        if ($signature) {
            event(new \\OVAC\\Guardrails\\Events\\ApprovalStepApproved($step, $signature));
        }

        // If threshold met, complete step and possibly the entire request
        $count = $step->signatures()->where('decision', 'approved')->count();
        if ($count >= (int) $step->threshold) {
            $step->status = 'completed';
            $step->completed_at = now();
            $step->save();

            $allDone = !$step->request->steps()->where('status', 'pending')->exists();
            if ($allDone) {
                $req = $step->request;
                $req->state = 'approved';
                $req->save();

                // Apply captured changes to the target model now that the flow is complete
                if ($model = $req->approvable) {
                    if (method_exists($model, 'withoutHumanGuard')) {
                        $model->withoutHumanGuard();
                    }
                    foreach ((array) $req->new_data as $k => $v) {
                        $model->{$k} = $v;
                    }
                    $model->save();
                }

                // Event hook when a request is fully approved
                event(new \\OVAC\\Guardrails\\Events\\ApprovalRequestCompleted($req));
            }
        }

        return response()->json(['success' => true, 'approved' => true]);
    }
}
