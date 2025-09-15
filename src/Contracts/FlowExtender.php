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
/**
 * Contract for building a multi-step approval flow.
 */
interface FlowExtender
{
    /**
     * Create a new builder instance.
     *
     * @return static
     */
    public static function make(): static;

    /**
     * Set the authentication guard used for signer checks.
     *
     * @param string $guard Guard name registered in auth.guards
     * @return static
     */
    public function guard(string $guard): static;

    /**
     * Append one or more permissions (all-of by default).
     *
     * @param array|string $permissions Permission names
     * @return static
     */
    public function permissions(array|string $permissions): static;

    /**
     * Replace the permission list.
     *
     * @param array|string $permissions Permission names
     * @return static
     */
    public function setPermissions(array|string $permissions): static;

    /**
     * Append one or more roles (all-of by default).
     *
     * @param array|string $roles Role names
     * @return static
     */
    public function roles(array|string $roles): static;

    /**
     * Replace the role list.
     *
     * @param array|string $roles Role names
     * @return static
     */
    public function setRoles(array|string $roles): static;

    /**
     * Finalize current step and push to flow.
     *
     * @param int|null $threshold Minimum approvals required for this step
     * @param string|null $name Display name for the step
     * @param array $meta Additional behavior flags
     * @return static
     */
    public function toStep(?int $threshold = 1, ?string $name = null, array $meta = []): static;

    /**
     * Add a normalized step array directly.
     *
     * @param array $step Normalized step array
     * @return static
     */
    public function addStep(array $step): static;

    /**
     * Build the flow array.
     *
     * @return array<int,array<string,mixed>>
     */
    public function build(): array;
}
