<?php

namespace OVAC\Guardrails\Http\Controllers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use OVAC\Guardrails\Events\ApprovalRequestCompleted;
use OVAC\Guardrails\Events\ApprovalStepApproved;
use OVAC\Guardrails\Models\ApprovalRequest;
use OVAC\Guardrails\Models\ApprovalSignature;
use OVAC\Guardrails\Models\ApprovalStep;
use OVAC\Guardrails\Support\Auth as GuardrailsAuth;
use OVAC\Guardrails\Support\SigningPolicy;

/**
 * API endpoints for listing and approving pending Guardrails requests.
 *
 * This controller mirrors Laravel's authentication controllers and exposes
 * granular protected hooks so applications can extend and override
 * specific behaviours without copying the entire implementation.
 */
class GuardrailApprovalsController extends Controller
{
    /**
     * Return a paginated list of pending approval requests.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function index(Request $request)
    {
        $user = $this->getAuthenticatedUser($request);
        if (!$user) {
            return $this->unauthenticatedResponse($request);
        }

        if (!$this->authorizeIndex($request, $user)) {
            return $this->forbiddenResponse($request);
        }

        $perPage = $this->resolvePerPage($request);
        $query = $this->newIndexQuery($request, $user);
        $results = $this->paginateIndexResults($query, $perPage, $request, $user);

        return $this->indexResponse($results, $request, $user);
    }

    /**
     * Approve a specific step within an approval request.
     *
     * @param  Request  $request
     * @param  int  $requestId
     * @param  int  $stepId
     * @return JsonResponse
     */
    public function approveStep(Request $request, int $requestId, int $stepId)
    {
        $payload = $this->validateApproveStep($request);

        $user = $this->getAuthenticatedUser($request);
        if (!$user) {
            return $this->unauthenticatedResponse($request);
        }

        if (!$this->authorizeApprove($request, $user)) {
            return $this->forbiddenResponse($request);
        }

        $step = $this->resolveApprovalStep($requestId, $stepId, $request, $user);

        if ($response = $this->ensureStepIsPending($step, $request, $user)) {
            return $response;
        }

        if (!$this->canUserSignStep($user, $step)) {
            return $this->ineligibleSignerResponse($request, $user, $step);
        }

        $signature = $this->recordApprovalSignature($step, $user, $payload, $request);
        $this->fireStepApprovedEvent($step, $signature, $request, $user);

        if ($this->stepHasReachedThreshold($step, $signature, $request, $user)) {
            $this->finalizeStep($step, $signature, $request, $user);
        }

        return $this->approveStepResponse($request, $user, $step, $signature);
    }

    /**
     * Reject a specific step within an approval request.
     *
     * @param  Request  $request
     * @param  int  $requestId
     * @param  int  $stepId
     * @return JsonResponse
     */
    public function rejectStep(Request $request, int $requestId, int $stepId)
    {
        $payload = $this->validateRejectStep($request);

        $user = $this->getAuthenticatedUser($request);
        if (!$user) {
            return $this->unauthenticatedResponse($request);
        }

        if (!$this->authorizeReject($request, $user)) {
            return $this->forbiddenResponse($request);
        }

        $step = $this->resolveApprovalStep($requestId, $stepId, $request, $user);

        if ($response = $this->ensureStepIsPending($step, $request, $user)) {
            return $response;
        }

        if (!$this->canUserSignStep($user, $step)) {
            return $this->ineligibleSignerResponse($request, $user, $step);
        }

        $signature = $this->recordRejectionSignature($step, $user, $payload, $request);

        $shouldFinalize = $this->shouldFinalizeRejectedStep($step, $signature, $request, $user);

        if ($shouldFinalize) {
            $this->finalizeRejectedStep($step, $signature, $request, $user);
        }

        $step = $step->refresh();

        $this->fireStepRejectedEvent($step, $signature, $request, $user);

        return $this->rejectStepResponse($request, $user, $step, $signature);
    }

    /**
     * Resolve the guard that should be used for authentication.
     *
     * @return string|null
     */
    protected function guardName(): ?string
    {
        return GuardrailsAuth::guardName();
    }

    /**
     * Retrieve the authenticated user for the request.
     *
     * @param  Request  $request
     * @return Authenticatable|null
     */
    protected function getAuthenticatedUser(Request $request): ?Authenticatable
    {
        return $request->user($this->guardName());
    }

    /**
     * Default unauthenticated response.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    protected function unauthenticatedResponse(Request $request): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
    }

    /**
     * Default forbidden response.
     *
     * @param  Request  $request
     * @param  string  $message
     * @return JsonResponse
     */
    protected function forbiddenResponse(Request $request, string $message = 'Forbidden.'): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message], 403);
    }

    /**
     * Ability required to list approval requests.
     *
     * @return string|null
     */
    protected function viewAbility(): ?string
    {
        return config('guardrails.permissions.view');
    }

    /**
     * Ability required to approve a step.
     *
     * @return string|null
     */
    protected function signAbility(): ?string
    {
        return config('guardrails.permissions.sign');
    }

    /**
     * Ability required to reject a step.
     *
     * @return string|null
     */
    protected function rejectAbility(): ?string
    {
        return $this->signAbility();
    }

    /**
     * Determine if a user can view the approvals listing.
     *
     * @param  Request  $request
     * @param  Authenticatable  $user
     * @return bool
     */
    protected function authorizeIndex(Request $request, Authenticatable $user): bool
    {
        return $this->userHasAbility($user, $this->viewAbility());
    }

    /**
     * Determine if a user can approve a step.
     *
     * @param  Request  $request
     * @param  Authenticatable  $user
     * @return bool
     */
    protected function authorizeApprove(Request $request, Authenticatable $user): bool
    {
        return $this->userHasAbility($user, $this->signAbility());
    }

    /**
     * Determine if a user can reject a step.
     *
     * @param  Request  $request
     * @param  Authenticatable  $user
     * @return bool
     */
    protected function authorizeReject(Request $request, Authenticatable $user): bool
    {
        return $this->userHasAbility($user, $this->rejectAbility());
    }

    /**
     * Check whether the user has the given ability.
     *
     * @param  Authenticatable  $user
     * @param  string|null  $ability
     * @return bool
     */
    protected function userHasAbility(Authenticatable $user, ?string $ability): bool
    {
        if (!$ability) {
            return true;
        }

        if (!method_exists($user, 'can')) {
            return true;
        }

        return (bool) $user->can($ability);
    }

    /**
     * Resolve per-page pagination size.
     *
     * @param  Request  $request
     * @return int
     */
    protected function resolvePerPage(Request $request): int
    {
        return min((int) $request->query('per_page', 25), 100);
    }

    /**
     * Base query for the approvals listing.
     *
     * @param  Request  $request
     * @param  Authenticatable  $user
     * @return Builder
     */
    protected function newIndexQuery(Request $request, Authenticatable $user): Builder
    {
        return ApprovalRequest::query()
            ->with(['approvable', 'steps.signatures'])
            ->where('state', 'pending')
            ->latest('id');
    }

    /**
     * Paginate results for the approvals listing.
     *
     * @param  Builder  $query
     * @param  int  $perPage
     * @param  Request  $request
     * @param  Authenticatable  $user
     * @return LengthAwarePaginator
     */
    protected function paginateIndexResults(Builder $query, int $perPage, Request $request, Authenticatable $user): LengthAwarePaginator
    {
        $collection = $query->get();
        /** @var array<int, ApprovalRequest> $items */
        $items = $collection->all();
        $filtered = $this->filterApprovalsForUser($items, $request, $user);

        $perPage = max(1, $perPage);
        $page = LengthAwarePaginator::resolveCurrentPage();
        $offset = ($page - 1) * $perPage;

        $paginatedItems = $filtered->slice($offset, $perPage)->values();

        return new LengthAwarePaginator(
            $paginatedItems,
            $filtered->count(),
            $perPage,
            $page,
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'pageName' => 'page',
            ]
        );
    }

    /**
     * Response payload for the approvals index.
     *
     * @param  LengthAwarePaginator  $results
     * @param  Request  $request
     * @param  Authenticatable  $user
     * @return JsonResponse
     */
    protected function indexResponse(LengthAwarePaginator $results, Request $request, Authenticatable $user): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $results]);
    }

    /**
     * Validate the approve step request payload.
     *
     * @param  Request  $request
     * @return array
     */
    protected function validateApproveStep(Request $request): array
    {
        return $request->validate(['comment' => 'nullable|string|max:1000']);
    }

    /**
     * Validate the reject step request payload.
     *
     * @param  Request  $request
     * @return array
     */
    protected function validateRejectStep(Request $request): array
    {
        return $request->validate(['comment' => 'nullable|string|max:1000']);
    }

    /**
     * Locate the approval step instance.
     *
     * @param  int  $requestId
     * @param  int  $stepId
     * @param  Request  $request
     * @param  Authenticatable  $user
     * @return ApprovalStep
     */
    protected function resolveApprovalStep(int $requestId, int $stepId, Request $request, Authenticatable $user): ApprovalStep
    {
        return ApprovalStep::where('request_id', $requestId)->findOrFail($stepId);
    }

    /**
     * Ensure the step is still pending before signing.
     *
     * @param  ApprovalStep  $step
     * @param  Request  $request
     * @param  Authenticatable  $user
     * @return JsonResponse|null
     */
    protected function ensureStepIsPending(ApprovalStep $step, Request $request, Authenticatable $user): ?JsonResponse
    {
        if ($step->status === 'pending') {
            return null;
        }

        return $this->stepAlreadyFinalizedResponse($request, $user, $step);
    }

    /**
     * Default response when the step is no longer pending.
     *
     * @param  Request  $request
     * @param  Authenticatable  $user
     * @param  ApprovalStep  $step
     * @return JsonResponse
     */
    protected function stepAlreadyFinalizedResponse(Request $request, Authenticatable $user, ApprovalStep $step): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Step already finalized.'], 422);
    }

    /**
     * Determine if the user may sign the given step.
     *
     * @param  Authenticatable  $user
     * @param  ApprovalStep  $step
     * @return bool
     */
    protected function canUserSignStep(Authenticatable $user, ApprovalStep $step): bool
    {
        $signers = (array) ($step->meta['signers'] ?? []);

        return SigningPolicy::canSign($user, $signers, $step);
    }

    /**
     * Response for ineligible signers.
     *
     * @param  Request  $request
     * @param  Authenticatable  $user
     * @param  ApprovalStep  $step
     * @return JsonResponse
     */
    protected function ineligibleSignerResponse(Request $request, Authenticatable $user, ApprovalStep $step): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'You are not eligible to sign this step.'], 403);
    }

    /**
     * Store or update the approval signature.
     *
     * @param  ApprovalStep  $step
     * @param  Authenticatable  $user
     * @param  array  $payload
     * @param  Request  $request
     * @return ApprovalSignature
     */
    protected function recordApprovalSignature(ApprovalStep $step, Authenticatable $user, array $payload, Request $request): ApprovalSignature
    {
        $signerId = $this->resolveUserIdentifier($user);

        return ApprovalSignature::updateOrCreate(
            ['step_id' => $step->id, 'signer_id' => $signerId],
            [
                'decision' => 'approved',
                'signed_at' => now(),
                'comment' => $payload['comment'] ?? null,
            ]
        );
    }

    /**
     * Dispatch the step approved event.
     *
     * @param  ApprovalStep  $step
     * @param  ApprovalSignature  $signature
     * @param  Request  $request
     * @param  Authenticatable  $user
     * @return void
     */
    protected function fireStepApprovedEvent(ApprovalStep $step, ApprovalSignature $signature, Request $request, Authenticatable $user): void
    {
        event(new ApprovalStepApproved($step, $signature));
    }

    /**
     * Check whether the step has reached its approval threshold.
     *
     * @param  ApprovalStep  $step
     * @param  ApprovalSignature  $signature
     * @param  Request  $request
     * @param  Authenticatable  $user
     * @return bool
     */
    protected function stepHasReachedThreshold(ApprovalStep $step, ApprovalSignature $signature, Request $request, Authenticatable $user): bool
    {
        $count = $step->signatures()->where('decision', 'approved')->count();

        return $count >= (int) $step->threshold;
    }

    /**
     * Determine whether accumulated rejections should finalize the step.
     *
     * @param  ApprovalStep       $step
     * @param  ApprovalSignature  $signature
     * @param  Request            $request
     * @param  Authenticatable    $user
     * @return bool
     */
    protected function shouldFinalizeRejectedStep(ApprovalStep $step, ApprovalSignature $signature, Request $request, Authenticatable $user): bool
    {
        $thresholds = $this->resolveRejectionThresholds($step, $request, $user);
        $count = $this->rejectionCount($step);

        return $count >= $thresholds['min'];
    }

    /**
     * Finalize the step and possibly the parent request when approvals are complete.
     *
     * @param  ApprovalStep  $step
     * @param  ApprovalSignature  $signature
     * @param  Request  $request
     * @param  Authenticatable  $user
     * @return void
     */
    protected function finalizeStep(ApprovalStep $step, ApprovalSignature $signature, Request $request, Authenticatable $user): void
    {
        $this->markStepCompleted($step, $signature, $request, $user);
        $this->handleStepCompletion($step, $signature, $request, $user);
    }

    /**
     * Mark the step as completed.
     *
     * @param  ApprovalStep  $step
     * @param  ApprovalSignature  $signature
     * @param  Request  $request
     * @param  Authenticatable  $user
     * @return void
     */
    protected function markStepCompleted(ApprovalStep $step, ApprovalSignature $signature, Request $request, Authenticatable $user): void
    {
        $step->status = 'completed';
        $step->completed_at = now();
        $step->save();
    }

    /**
     * Handle any follow-up work after a step has been completed.
     *
     * @param  ApprovalStep  $step
     * @param  ApprovalSignature  $signature
     * @param  Request  $request
     * @param  Authenticatable  $user
     * @return void
     */
    protected function handleStepCompletion(ApprovalStep $step, ApprovalSignature $signature, Request $request, Authenticatable $user): void
    {
        if ($this->stepHasPendingSiblings($step, $signature, $request, $user)) {
            return;
        }

        $approvalRequest = $step->request;

        if ($approvalRequest) {
            $this->completeApprovalRequest($approvalRequest, $step, $signature, $request, $user);
        }
    }

    /**
     * Determine whether any sibling steps are still pending.
     *
     * @param  ApprovalStep  $step
     * @param  ApprovalSignature  $signature
     * @param  Request  $request
     * @param  Authenticatable  $user
     * @return bool
     */
    protected function stepHasPendingSiblings(ApprovalStep $step, ApprovalSignature $signature, Request $request, Authenticatable $user): bool
    {
        return $step->request
            ? $step->request->steps()->where('status', 'pending')->exists()
            : false;
    }

    /**
     * Complete the parent approval request.
     *
     * @param  ApprovalRequest  $approvalRequest
     * @param  ApprovalStep  $step
     * @param  ApprovalSignature  $signature
     * @param  Request  $request
     * @param  Authenticatable  $user
     * @return void
     */
    protected function completeApprovalRequest(ApprovalRequest $approvalRequest, ApprovalStep $step, ApprovalSignature $signature, Request $request, Authenticatable $user): void
    {
        $this->markRequestApproved($approvalRequest, $request, $user);
        $this->applyApprovedModelChanges($approvalRequest, $step, $signature, $request, $user);
        $this->fireRequestCompletedEvent($approvalRequest, $step, $signature, $request, $user);
    }

    /**
     * Mark the approval request as approved.
     *
     * @param  ApprovalRequest  $approvalRequest
     * @param  Request  $request
     * @param  Authenticatable  $user
     * @return void
     */
    protected function markRequestApproved(ApprovalRequest $approvalRequest, Request $request, Authenticatable $user): void
    {
        $approvalRequest->state = 'approved';
        $approvalRequest->save();
    }

    /**
     * Apply the approved data to the underlying model.
     *
     * @param  ApprovalRequest  $approvalRequest
     * @param  ApprovalStep  $step
     * @param  ApprovalSignature  $signature
     * @param  Request  $request
     * @param  Authenticatable  $user
     * @return void
     */
    protected function applyApprovedModelChanges(ApprovalRequest $approvalRequest, ApprovalStep $step, ApprovalSignature $signature, Request $request, Authenticatable $user): void
    {
        $model = $approvalRequest->approvable;

        if (!$model) {
            return;
        }

        $changes = (array) $approvalRequest->new_data;

        $applyChanges = function ($instance) use ($changes): void {
            $this->applyChangesToModel($instance, $changes);
        };

        if (method_exists($model, 'withoutGuardrail')) {
            $model->withoutGuardrail($applyChanges);
        } else {
            $applyChanges($model);
        }
    }

    /**
     * Apply the provided changes to the model instance.
     *
     * @param  object  $model
     * @param  array  $changes
     * @return void
     */
    protected function applyChangesToModel($model, array $changes): void
    {
        foreach ($changes as $key => $value) {
            $model->{$key} = $value;
        }

        $model->save();
    }

    /**
     * Dispatch the approval request completed event.
     *
     * @param  ApprovalRequest  $approvalRequest
     * @param  ApprovalStep  $step
     * @param  ApprovalSignature  $signature
     * @param  Request  $request
     * @param  Authenticatable  $user
     * @return void
     */
    protected function fireRequestCompletedEvent(ApprovalRequest $approvalRequest, ApprovalStep $step, ApprovalSignature $signature, Request $request, Authenticatable $user): void
    {
        event(new ApprovalRequestCompleted($approvalRequest));
    }

    /**
     * Successful response for approving a step.
     *
     * @param  Request  $request
     * @param  Authenticatable  $user
     * @param  ApprovalStep  $step
     * @param  ApprovalSignature  $signature
     * @return JsonResponse
     */
    protected function approveStepResponse(Request $request, Authenticatable $user, ApprovalStep $step, ApprovalSignature $signature): JsonResponse
    {
        return response()->json(['success' => true, 'approved' => true]);
    }

    /**
     * Store or update the rejection signature.
     *
     * @param  ApprovalStep  $step
     * @param  Authenticatable  $user
     * @param  array  $payload
     * @param  Request  $request
     * @return ApprovalSignature
     */
    protected function recordRejectionSignature(ApprovalStep $step, Authenticatable $user, array $payload, Request $request): ApprovalSignature
    {
        $signerId = $this->resolveUserIdentifier($user);

        return ApprovalSignature::updateOrCreate(
            ['step_id' => $step->id, 'signer_id' => $signerId],
            [
                'decision' => 'rejected',
                'signed_at' => now(),
                'comment' => $payload['comment'] ?? null,
            ]
        );
    }

    /**
     * Resolve rejection thresholds for the provided step.
     *
     * @param  ApprovalStep     $step
     * @param  Request          $request
     * @param  Authenticatable  $user
     * @return array{min:int, max:?int}
     */
    protected function resolveRejectionThresholds(ApprovalStep $step, Request $request, Authenticatable $user): array
    {
        $meta = (array) ($step->meta ?? []);
        $threshold = max(1, (int) ($step->threshold ?? 1));

        $minimum = $meta['rejection_min'] ?? null;
        $maximum = $meta['rejection_max'] ?? null;

        if ($minimum === null || (int) $minimum < 1) {
            $minimum = (int) ceil($threshold / 2);
        } else {
            $minimum = (int) $minimum;
        }

        if ($maximum !== null) {
            $maximum = max($minimum, (int) $maximum);
        } else {
            $maximum = null;
        }

        return ['min' => $minimum, 'max' => $maximum];
    }

    /**
     * Count rejection signatures for the given step.
     *
     * @param  ApprovalStep  $step
     * @return int
     */
    protected function rejectionCount(ApprovalStep $step): int
    {
        return $step->signatures()->where('decision', 'rejected')->count();
    }

    /**
     * Dispatch the step rejected event.
     *
     * @param  ApprovalStep  $step
     * @param  ApprovalSignature  $signature
     * @param  Request  $request
     * @param  Authenticatable  $user
     * @return void
     */
    protected function fireStepRejectedEvent(ApprovalStep $step, ApprovalSignature $signature, Request $request, Authenticatable $user): void
    {
        event(new \OVAC\Guardrails\Events\ApprovalStepRejected($step, $signature));
    }

    /**
     * Finalize a step that has been rejected.
     *
     * @param  ApprovalStep  $step
     * @param  ApprovalSignature  $signature
     * @param  Request  $request
     * @param  Authenticatable  $user
     * @return void
     */
    protected function finalizeRejectedStep(ApprovalStep $step, ApprovalSignature $signature, Request $request, Authenticatable $user): void
    {
        $step->status = 'rejected';
        $step->completed_at = now();
        $step->save();

        if ($step->request) {
            $this->rejectApprovalRequest($step->request, $step, $signature, $request, $user);
        }
    }

    /**
     * Handle a rejection of the parent approval request.
     *
     * @param  ApprovalRequest  $approvalRequest
     * @param  ApprovalStep  $step
     * @param  ApprovalSignature  $signature
     * @param  Request  $request
     * @param  Authenticatable  $user
     * @return void
     */
    protected function rejectApprovalRequest(ApprovalRequest $approvalRequest, ApprovalStep $step, ApprovalSignature $signature, Request $request, Authenticatable $user): void
    {
        $approvalRequest->state = 'rejected';
        $approvalRequest->save();

        $this->fireRequestRejectedEvent($approvalRequest, $step, $signature, $request, $user);
    }

    /**
     * Dispatch the approval request rejected event.
     *
     * @param  ApprovalRequest  $approvalRequest
     * @param  ApprovalStep  $step
     * @param  ApprovalSignature  $signature
     * @param  Request  $request
     * @param  Authenticatable  $user
     * @return void
     */
    protected function fireRequestRejectedEvent(ApprovalRequest $approvalRequest, ApprovalStep $step, ApprovalSignature $signature, Request $request, Authenticatable $user): void
    {
        event(new \OVAC\Guardrails\Events\ApprovalRequestRejected($approvalRequest, $step, $signature));
    }

    /**
     * Successful response for rejecting a step.
     *
     * @param  Request  $request
     * @param  Authenticatable  $user
     * @param  ApprovalStep  $step
     * @param  ApprovalSignature  $signature
     * @return JsonResponse
     */
    protected function rejectStepResponse(Request $request, Authenticatable $user, ApprovalStep $step, ApprovalSignature $signature): JsonResponse
    {
        $thresholds = $this->resolveRejectionThresholds($step, $request, $user);
        $count = $this->rejectionCount($step);

        return response()->json([
            'success' => true,
            'rejected' => $step->status === 'rejected',
            'rejections' => [
                'count' => $count,
                'required' => $thresholds['min'],
                'maximum' => $thresholds['max'],
            ],
        ]);
    }

    /**
     * Filter approval requests so only ones relevant to the user remain.
     *
     * @param  iterable<int, ApprovalRequest>  $requests
     * @param  Request  $request
     * @param  Authenticatable  $user
     * @return Collection<int, ApprovalRequest>
     */
    protected function filterApprovalsForUser(iterable $requests, Request $request, Authenticatable $user): Collection
    {
        return Collection::make($requests)
            ->filter(function (ApprovalRequest $approvalRequest) use ($user) {
                return $this->requestRelatesToUser($approvalRequest, $user);
            })
            ->values();
    }

    /**
     * Determine whether the approval request is relevant to the authenticated user.
     *
     * @param  ApprovalRequest  $approvalRequest
     * @param  Authenticatable  $user
     * @return bool
     */
    protected function requestRelatesToUser(ApprovalRequest $approvalRequest, Authenticatable $user): bool
    {
        $userId = (string) $this->resolveUserIdentifier($user);

        if ($userId !== '' && (string) $approvalRequest->initiator_id === $userId) {
            return true;
        }

        $stepsRelation = $approvalRequest->steps ?? [];
        $steps = $stepsRelation instanceof Collection ? $stepsRelation : Collection::make($stepsRelation);

        foreach ($steps as $step) {
            if ($this->stepRelatesToUser($step, $approvalRequest, $user)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine whether the approval step is relevant to the authenticated user.
     *
     * @param  ApprovalStep  $step
     * @param  ApprovalRequest  $approvalRequest
     * @param  Authenticatable  $user
     * @return bool
     */
    protected function stepRelatesToUser(ApprovalStep $step, ApprovalRequest $approvalRequest, Authenticatable $user): bool
    {
        $userId = (string) $this->resolveUserIdentifier($user);

        if ($userId !== '') {
            $signaturesRelation = $step->signatures ?? [];
            $signatures = $signaturesRelation instanceof Collection ? $signaturesRelation : Collection::make($signaturesRelation);
            $signatureMatch = $signatures->first(function ($signature) use ($userId) {
                return (string) ($signature->signer_id ?? '') === $userId;
            });
            if ($signatureMatch) {
                return true;
            }
        }

        if ($step->status === 'pending' && $this->canUserSignStep($user, $step)) {
            return true;
        }

        if (
            $userId !== '' &&
            (string) $approvalRequest->initiator_id === $userId &&
            ($step->meta['include_initiator'] ?? false)
        ) {
            return true;
        }

        return false;
    }

    /**
     * Resolve the unique identifier for the authenticated user as a string.
     *
     * @param  Authenticatable  $user
     * @return string
     */
    protected function resolveUserIdentifier(Authenticatable $user): string
    {
        if (method_exists($user, 'getAuthIdentifier')) {
            $identifier = $user->getAuthIdentifier();
            if ($identifier !== null) {
                return (string) $identifier;
            }
        }

        if (isset($user->id)) {
            return (string) $user->id;
        }

        return '';
    }
}
