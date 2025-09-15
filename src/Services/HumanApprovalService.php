<?php

namespace OVAC\Guardrails\Services;

use OVAC\Guardrails\Models\ApprovalRequest;
use OVAC\Guardrails\Models\ApprovalStep;
use OVAC\Guardrails\Models\ApprovalSignature;
use OVAC\Guardrails\Support\SigningPolicy;

/**
 * Core service to capture Guardrails approval requests and initialize steps.
 */
class HumanApprovalService
{
    /**
     * Capture an approval for a model mutation and initialize steps.
     *
     * @param object $model Eloquent-like model with original values and key
     * @param array $dirty The subset of attributes involved in the mutation
     * @param string $event creating|updating|custom (for audit context)
     * @return ApprovalRequest The created request with steps persisted
     */
    public static function capture($model, array $dirty, string $event = 'updating'): ApprovalRequest
    {
        $original = collect($model->getOriginal())->only(array_keys($dirty))->toArray();

        $request = new ApprovalRequest();
        $request->approvable_type = get_class($model);
        $request->approvable_id = method_exists($model, 'getKey') ? $model->getKey() : null;
        $request->actor_staff_id = optional(auth('staff')->user())->id;
        $request->state = 'pending';
        $request->new_data = $dirty;
        $request->original_data = $original;
        $request->context = [
            'event' => $event,
            'route' => request()->route()?->getName(),
        ];
        $request->save();

        // Build steps from model-defined rules or a default one
        $flow = method_exists($model, 'humanApprovalFlow') ? (array) $model->humanApprovalFlow($dirty, $event) : [];
        if (empty($flow)) {
            $flow = [[
                'name' => 'Default',
                'threshold' => 1,
                'signers' => ['permission' => 'approvals.manage'],
            ]];
        }

        $level = 1;
        foreach ($flow as $stepCfg) {
            $step = new ApprovalStep();
            $step->request_id = $request->id;
            $step->level = $level++;
            $step->name = (string) ($stepCfg['name'] ?? 'Step');
            $step->threshold = (int) ($stepCfg['threshold'] ?? 1);
            $step->status = 'pending';
            $step->meta = [
                'signers' => $stepCfg['signers'] ?? [],
                'include_initiator' => (bool) (($stepCfg['meta']['include_initiator'] ?? $stepCfg['include_initiator'] ?? false)),
                'preapprove_initiator' => (bool) (($stepCfg['meta']['preapprove_initiator'] ?? $stepCfg['preapprove_initiator'] ?? true)),
            ];
            $step->save();

            // Optionally pre-approve the initiator and count toward threshold
            $actorId = $request->actor_staff_id;
            if ($actorId && ($step->meta['include_initiator'] ?? false)) {
                $actor = \App\Models\Staff::find($actorId);
                $signers = (array) ($step->meta['signers'] ?? []);
                if ($actor && SigningPolicy::canSign($actor, $signers, $step)) {
                    if ($step->meta['preapprove_initiator'] ?? true) {
                        ApprovalSignature::updateOrCreate(
                            ['step_id' => $step->id, 'staff_id' => $actor->id],
                            ['decision' => 'approved', 'signed_at' => now(), 'comment' => 'Auto-counted initiator']
                        );

                        // If threshold reached by pre-approval, finalize step
                        $count = $step->signatures()->where('decision', 'approved')->count();
                        if ($count >= (int) $step->threshold) {
                            $step->status = 'completed';
                            $step->completed_at = now();
                            $step->save();
                        }
                    }
                }
            }
        }

        // Fire domain event for auditing/integrations
        event(new \\OVAC\\Guardrails\\Events\\ApprovalRequestCaptured($request));

        return $request;
    }
}
