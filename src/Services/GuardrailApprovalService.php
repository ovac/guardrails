<?php

namespace OVAC\Guardrails\Services;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use OVAC\Guardrails\Events\ApprovalRequestRejected;
use OVAC\Guardrails\Events\ApprovalStepRejected;
use OVAC\Guardrails\Models\ApprovalRequest;
use OVAC\Guardrails\Models\ApprovalSignature;
use OVAC\Guardrails\Models\ApprovalStep;
use OVAC\Guardrails\Support\SigningPolicy;

/**
 * Core service to capture Guardrails approval requests and initialize steps (Guardrails).
 */
class GuardrailApprovalService
{
    /**
     * Capture an approval for a model mutation and initialize steps.
     *
     * @param  object  $model    Eloquent-like model with original values and key.
     * @param  array<string, mixed>  $dirty    Subset of attributes involved in the mutation.
     * @param  string  $event    creating|updating|custom (used for audit context).
     * @param  array<string, mixed>  $options  Optional overrides such as description/meta payload.
     * @return \OVAC\Guardrails\Models\ApprovalRequest Created request with steps persisted.
     */
    public static function capture($model, array $dirty, string $event = 'updating', array $options = []): ApprovalRequest
    {
        $original = collect($model->getOriginal())->only(array_keys($dirty))->toArray();

        $flowOverride = $options['flow'] ?? null;
        unset($options['flow']);

        $requestForContext = $options['request'] ?? null;
        unset($options['request']);

        $extraContext = (array) ($options['context'] ?? []);
        unset($options['context']);

        $description = $options['description'] ?? null;
        if ($description === null && method_exists($model, 'guardrailApprovalDescription')) {
            $description = $model->guardrailApprovalDescription($dirty, $event);
        }
        if ($description === null || $description === '') {
            $description = self::defaultDescription($model, $dirty, $event);
        }

        $meta = $options['meta'] ?? null;
        if (!array_key_exists('meta', $options)) {
            if ($meta === null && method_exists($model, 'guardrailApprovalMeta')) {
                $meta = $model->guardrailApprovalMeta($dirty, $event);
            }
        }

        $request = new ApprovalRequest();
        $request->approvable_type = get_class($model);
        $request->approvable_id = method_exists($model, 'getKey') ? $model->getKey() : null;
        $initiator = \OVAC\Guardrails\Support\Auth::user();
        $request->initiator_id = $initiator ? self::resolveSignerId($initiator) : null;
        $request->state = 'pending';
        if ($description !== '') {
            $request->description = (string) $description;
        }
        $request->new_data = $dirty;
        $request->original_data = $original;
        $context = array_merge([
            'event' => $event,
            'route' => self::resolveRouteName($requestForContext, $extraContext),
        ], $extraContext);
        $request->context = $context;
        if ($meta !== null) {
            $request->meta = $meta;
        }
        $request->save();

        // Build steps from model-defined rules or a default one (prefer guardrailApprovalFlow)
        if ($flowOverride !== null) {
            $flow = (array) $flowOverride;
        } elseif (method_exists($model, 'guardrailApprovalFlow')) {
            $flow = (array) $model->guardrailApprovalFlow($dirty, $event);
        } else {
            $flow = [];
        }
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
            $meta = (array) ($stepCfg['meta'] ?? []);
            $meta['include_initiator'] = (bool) ($meta['include_initiator'] ?? ($stepCfg['include_initiator'] ?? false));
            $meta['preapprove_initiator'] = (bool) ($meta['preapprove_initiator'] ?? ($stepCfg['preapprove_initiator'] ?? true));
            $meta['rejection_min'] = self::normalizeRejectionValue($meta['rejection_min'] ?? ($stepCfg['rejection_min'] ?? null));
            $meta['rejection_max'] = self::normalizeRejectionValue($meta['rejection_max'] ?? ($stepCfg['rejection_max'] ?? null));
            if ($meta['rejection_min'] !== null && $meta['rejection_max'] !== null && $meta['rejection_max'] < $meta['rejection_min']) {
                $meta['rejection_max'] = $meta['rejection_min'];
            }

            $step->meta = array_merge($meta, [
                'signers' => $stepCfg['signers'] ?? [],
            ]);
            $step->save();

            // Optionally pre-approve the initiator and count toward threshold
            $initiatorId = $request->initiator_id;
            if ($initiatorId && ($step->meta['include_initiator'] ?? false)) {
                $signers = (array) ($step->meta['signers'] ?? []);
                if ($initiator && SigningPolicy::canSign($initiator, $signers, $step)) {
                    if ($step->meta['preapprove_initiator'] ?? true) {
                        $initiatorSignerId = self::resolveSignerId($initiator);
                        if ($initiatorSignerId === null) {
                            continue;
                        }

                        ApprovalSignature::updateOrCreate(
                            ['step_id' => $step->id, 'signer_id' => $initiatorSignerId],
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
        event(new \OVAC\Guardrails\Events\ApprovalRequestCaptured($request));

        return $request;
    }

    /**
     * Normalise rejection threshold values, allowing null.
     *
     * @param  mixed  $value
     * @return int|null
     */
    protected static function normalizeRejectionValue(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $int = (int) $value;

        return $int >= 1 ? $int : null;
    }

    /**
     * Generate a fallback description when none is supplied.
     *
     * @param  object  $model    Model instance undergoing approval.
     * @param  array<string, mixed>  $dirty    Attributes captured for the request.
     * @param  string  $event    Lifecycle event name (creating/updating/custom).
     * @return string
     */
    protected static function defaultDescription($model, array $dirty, string $event): string
    {
        $modelName = class_basename($model);
        $attributes = array_keys($dirty);
        $eventLabel = match ($event) {
            'creating' => 'creation',
            'updating' => 'update',
            'deleting' => 'deletion',
            default => $event,
        };

        if (!empty($attributes)) {
            $attributeList = implode(', ', $attributes);
            return sprintf('%s %s requires approval (%s).', $modelName, $eventLabel, $attributeList);
        }

        return sprintf('%s %s requires approval.', $modelName, $eventLabel);
    }

    /**
     * Determine the most relevant route name for the captured request context.
     *
     * @param  object|null  $providedRequest  Explicit request instance provided via options.
     * @param  array<string, mixed>  $extraContext  Additional context supplied by the caller.
     * @return string|null
     */
    protected static function resolveRouteName($providedRequest, array $extraContext): ?string
    {
        if (!empty($extraContext['route'])) {
            return $extraContext['route'];
        }

        if ($providedRequest && method_exists($providedRequest, 'route')) {
            return optional($providedRequest->route())->getName();
        }

        if (function_exists('app') && app()->bound('request')) {
            return optional(app('request')->route())->getName();
        }

        return null;
    }

    /**
     * Reject an approval step with an optional comment and update related state/events.
     *
     * @param  ApprovalStep  $step
     * @param  Authenticatable  $user
     * @param  string|null  $comment
     * @param  bool  $validateEligibility  When true, ensure the user satisfies the signer policy.
     * @return ApprovalSignature
     *
     * @throws AuthorizationException
     * @throws \RuntimeException When the step is not pending.
     */
    public static function rejectStep(
        ApprovalStep $step,
        Authenticatable $user,
        ?string $comment = null,
        bool $validateEligibility = true
    ): ApprovalSignature {
        if ($step->status !== 'pending') {
            throw new \RuntimeException('Approval step is no longer pending.');
        }

        if ($validateEligibility) {
            $signers = (array) ($step->meta['signers'] ?? []);
            if (!SigningPolicy::canSign($user, $signers, $step)) {
                throw new AuthorizationException('You are not eligible to reject this step.');
            }
        }

        $signerId = self::resolveSignerId($user);
        if ($signerId === null) {
            throw new \RuntimeException('Unable to resolve signer identifier for rejection.');
        }

        $signature = ApprovalSignature::updateOrCreate(
            ['step_id' => $step->id, 'signer_id' => $signerId],
            [
                'decision' => 'rejected',
                'signed_at' => now(),
                'comment' => $comment,
            ]
        );

        $thresholds = self::computeRejectionThresholds($step);
        $rejectionCount = $step->signatures()->where('decision', 'rejected')->count();

        $approvalRequest = $step->request;
        $shouldFinalize = $rejectionCount >= $thresholds['min'];

        if ($shouldFinalize) {
            $step->status = 'rejected';
            $step->completed_at = now();
            $step->save();

            if ($approvalRequest && $approvalRequest->state !== 'rejected') {
                $approvalRequest->state = 'rejected';
                $approvalRequest->save();
            }
        }

        $refreshedStep = $step->refresh();

        event(new ApprovalStepRejected($refreshedStep, $signature));

        if ($shouldFinalize && $approvalRequest) {
            $refreshedRequest = $approvalRequest->refresh();
            event(new ApprovalRequestRejected($refreshedRequest, $refreshedStep, $signature));
        }

        return $signature;
    }

    /**
     * Resolve the signer identifier for the given user.
     *
     * @param  Authenticatable  $user
     * @return mixed
     */
    protected static function resolveSignerId(Authenticatable $user): mixed
    {
        if (method_exists($user, 'getAuthIdentifier')) {
            $identifier = $user->getAuthIdentifier();
            if ($identifier !== null) {
                return $identifier;
            }
        }

        return $user->id ?? null;
    }

    /**
     * Determine the rejection thresholds for the provided step.
     *
     * @param  ApprovalStep  $step
     * @return array{min:int, max:?int}
     */
    protected static function computeRejectionThresholds(ApprovalStep $step): array
    {
        $meta = (array) ($step->meta ?? []);
        $threshold = max(1, (int) ($step->threshold ?? 1));

        $min = $meta['rejection_min'] ?? null;
        $max = $meta['rejection_max'] ?? null;

        if ($min === null || (int) $min < 1) {
            $min = (int) ceil($threshold / 2);
        } else {
            $min = (int) $min;
        }

        if ($max !== null) {
            $max = max($min, (int) $max);
        } else {
            $max = null;
        }

        return ['min' => $min, 'max' => $max];
    }
}
