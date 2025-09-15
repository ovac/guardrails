<?php

namespace OVAC\Guardrails\Contracts;

/**
 * Contract for building a multi-step approval flow.
 *
 * Usage example:
 *
 * FlowExtensionBuilder::make()
 *   ->permissionsAny(['orders.manage','orders.escalate'])
 *   ->includeInitiator(true, true)
 *   ->toStep(2, 'Ops Review')
 *   ->rolesAny(['finance_manager','ops_manager'])
 *   ->toStep(1, 'Management')
 *   ->build();
 */
interface FlowExtender
{
    /** Create a new builder instance. */
    public static function make(): static;

    /** Set the authentication guard (default: staff). */
    public function guard(string $guard): static;

    /** Append one or more permissions (all-of by default). */
    public function permissions(array|string $permissions): static;

    /** Replace permissions list. */
    public function setPermissions(array|string $permissions): static;

    /** Append one or more roles (all-of by default). */
    public function roles(array|string $roles): static;

    /** Replace roles list. */
    public function setRoles(array|string $roles): static;

    /**
     * Finalize current step and push to flow.
     *
     * @param int|null $threshold Minimum approvals required for this step
     * @param string|null $name Display name for the step
     * @param array $meta Additional behavior flags
     */
    public function toStep(?int $threshold = 1, ?string $name = null, array $meta = []): static;

    /** Add a normalized step array directly. */
    public function addStep(array $step): static;

    /** Build the flow array. */
    public function build(): array;
}
