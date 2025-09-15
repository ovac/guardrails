<?php

namespace OVAC\Guardrails\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use OVAC\Guardrails\Models\ApprovalStep;
use OVAC\Guardrails\Support\SigningPolicy;

class ActorApprovalsController extends Controller
{
    public function index(Request $request)
    {
        $perPage = min((int) $request->query('per_page', 25), 100);

        $q = \OVAC\Guardrails\Models\ApprovalRequest::query()
            ->with(['approvable','steps.signatures'])
            ->where('state', 'pending')
            ->latest('id');

        $data = $q->paginate($perPage);
        return response()->json(['success' => true, 'data' => $data]);
    }

    public function approveStep(Request $request, int $requestId, int $stepId)
    {
        $request->validate(['comment' => 'nullable|string|max:1000']);
        $guard = \OVAC\Guardrails\Support\Auth::guardName();
        $user = $request->user($guard);
        $step = ApprovalStep::where('request_id', $requestId)->findOrFail($stepId);

        if ($step->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'Step already finalized.'], 422);
        }

        $signers = (array) ($step->meta['signers'] ?? []);
        if (!SigningPolicy::canSign($user, $signers, $step)) {
            return response()->json(['success' => false, 'message' => 'You are not eligible to sign this step.'], 403);
        }

        $signature = \OVAC\Guardrails\Models\ApprovalSignature::updateOrCreate(
            ['step_id' => $step->id, 'staff_id' => $user->id],
            ['decision' => 'approved', 'signed_at' => now(), 'comment' => $request->string('comment')]
        );
        event(new \OVAC\Guardrails\Events\ApprovalStepApproved($step, $signature));

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

                if ($model = $req->approvable) {
                    if (method_exists($model, 'withoutActorGuard')) {
                        $model->withoutActorGuard();
                    } elseif (method_exists($model, 'withoutHumanGuard')) {
                        $model->withoutHumanGuard();
                    }
                    foreach ((array) $req->new_data as $k => $v) {
                        $model->{$k} = $v;
                    }
                    $model->save();
                }

                event(new \OVAC\Guardrails\Events\ApprovalRequestCompleted($req));
            }
        }

        return response()->json(['success' => true, 'approved' => true]);
    }
}

