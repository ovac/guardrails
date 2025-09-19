<?php

namespace OVAC\Guardrails\Contracts;

/**
 * Contract for building a multi-step approval flow.
 *
 * Usage example:
 *
 * FlowBuilder::make()
 *   ->anyOfPermissions(['orders.manage','orders.escalate'])
 *   ->includeInitiator(true, true)
 *   ->signedBy(2, 'Ops Review')
 *   ->anyOfRoles(['finance_manager','ops_manager'])
 *   ->signedBy(1, 'Management')
 *   ->build();
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
     * @param  string  $guard  Guard name registered in auth.guards.
     * @return static
     */
    public function guard(string $guard): static;

    /**
     * Append one or more permissions (all-of by default).
     *
     * @param  array<int, string>|string  $permissions  Permission names.
     * @return static
     */
    public function permissions(array|string $permissions): static;

    /**
     * Replace the permission list.
     *
     * @param  array<int, string>|string  $permissions  Permission names.
     * @return static
     */
    public function setPermissions(array|string $permissions): static;

    /**
     * Append permissions and mark as any-of semantics.
     *
     * @param  array<int, string>|string  $permissions
     * @return static
     */
    public function anyOfPermissions(array|string $permissions): static;

    /**
     * Append one or more roles (all-of by default).
     *
     * @param  array<int, string>|string  $roles  Role names.
     * @return static
     */
    public function roles(array|string $roles): static;

    /**
     * Replace the role list.
     *
     * @param  array<int, string>|string  $roles  Role names.
     * @return static
     */
    public function setRoles(array|string $roles): static;

    /**
     * Append roles and mark as any-of semantics.
     *
     * @param  array<int, string>|string  $roles
     * @return static
     */
    public function anyOfRoles(array|string $roles): static;

    /**
     * Finalize current step and push to flow.
     *
     * @param  int|null  $threshold  Minimum approvals required for this step.
     * @param  string|null  $name  Display name for the step.
     * @param  array<string, mixed>  $meta  Additional behavior flags.
     * @return static
     */
    public function signedBy(?int $threshold = 1, ?string $name = null, array $meta = []): static;

    /**
     * Add a normalized step array directly.
     *
     * @param  array<string, mixed>  $step  Normalized step array.
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
