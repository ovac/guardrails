<?php

namespace OVAC\Guardrails\Services;

use OVAC\Guardrails\Contracts\FlowExtender;

/**
 * Fluent builder for multi-step Guardrails approval flows.
 *
 * Examples:
 * - Any-of permissions, count initiator:
 *   FlowExtensionBuilder::make()
 *     ->anyOfPermissions(['orders.manage','orders.escalate'])
 *     ->includeInitiator(true, true)
 *     ->toStep(2, 'Ops Review')
 *     ->build();
 *
 * - Any-of roles:
 *   FlowExtensionBuilder::make()->anyOfRoles(['ops_manager','finance_manager'])->toStep(1, 'Mgmt')->build();
 */
/**
 * Fluent builder for multi-step approval flows.
 */
class FlowExtensionBuilder implements FlowExtender
{
    protected array $steps = [];
    protected array $current = [
        'name' => null,
        'threshold' => 1,
        'signers' => [
            'guard' => 'staff',
            'permissions' => [],
            'permissions_mode' => 'all', // all|any
            'roles' => [],
            'roles_mode' => 'all', // all|any
            // Optional advanced constraints
            'same_permission_as_initiator' => false,
            'same_role_as_initiator' => false,
        ],
        'meta' => [
            // Step-level behavior flags
            'include_initiator' => false,
            'preapprove_initiator' => true,
        ],
    ];

    /**
     * Create a new builder instance.
     *
     * @return static
     */
    public static function make(): static
    {
        return new static();
    }

    /**
     * Set the auth guard to evaluate against when checking signers.
     *
     * @param string $guard Guard name defined in auth.guards
     * @return static
     */
    public function guard(string $guard): static
    {
        $this->current['signers']['guard'] = $guard;
        return $this;
    }

    /**
     * Append permission(s). All-of semantics by default.
     *
     * @param array|string $permissions Permission names
     * @return static
     */
    public function permissions(array|string $permissions): static
    {
        $perms = is_array($permissions) ? $permissions : [$permissions];
        $this->current['signers']['permissions'] = array_values(array_unique(array_merge($this->current['signers']['permissions'], $perms)));
        return $this;
    }

    /**
     * Replace permission set entirely.
     *
     * @param array|string $permissions Permission names
     * @return static
     */
    public function setPermissions(array|string $permissions): static
    {
        $this->current['signers']['permissions'] = is_array($permissions) ? array_values($permissions) : [$permissions];
        return $this;
    }

    /**
     * Append permissions and mark as any-of.
     *
     * @param array|string $permissions Permission names
     * @return static
     */
    public function anyOfPermissions(array|string $permissions): static
    {
        $this->permissions($permissions);
        $this->current['signers']['permissions_mode'] = 'any';
        return $this;
    }

    /**
     * Backwards-compatible alias.
     * @deprecated Use anyOfPermissions()
     */
    public function permissionsAny(array|string $permissions): static
    {
        return $this->anyOfPermissions($permissions);
    }

    /**
     * Require any-of permissions already configured.
     *
     * @return static
     */
    public function requireAnyPermissions(): static
    {
        $this->current['signers']['permissions_mode'] = 'any';
        return $this;
    }

    /**
     * Require all-of permissions already configured.
     *
     * @return static
     */
    public function requireAllPermissions(): static
    {
        $this->current['signers']['permissions_mode'] = 'all';
        return $this;
    }

    /**
     * Append role(s). All-of semantics by default.
     *
     * @param array|string $roles Role names
     * @return static
     */
    public function roles(array|string $roles): static
    {
        $rs = is_array($roles) ? $roles : [$roles];
        $this->current['signers']['roles'] = array_values(array_unique(array_merge($this->current['signers']['roles'], $rs)));
        return $this;
    }

    /**
     * Replace role set entirely.
     *
     * @param array|string $roles Role names
     * @return static
     */
    public function setRoles(array|string $roles): static
    {
        $this->current['signers']['roles'] = is_array($roles) ? array_values($roles) : [$roles];
        return $this;
    }

    /**
     * Append roles and mark as any-of.
     *
     * @param array|string $roles Role names
     * @return static
     */
    public function anyOfRoles(array|string $roles): static
    {
        $this->roles($roles);
        $this->current['signers']['roles_mode'] = 'any';
        return $this;
    }

    /**
     * Backwards-compatible alias.
     * @deprecated Use anyOfRoles()
     */
    public function rolesAny(array|string $roles): static
    {
        return $this->anyOfRoles($roles);
    }

    /**
     * Require any-of roles already configured.
     *
     * @return static
     */
    public function requireAnyRoles(): static
    {
        $this->current['signers']['roles_mode'] = 'any';
        return $this;
    }

    /**
     * Require all-of roles already configured.
     *
     * @return static
     */
    public function requireAllRoles(): static
    {
        $this->current['signers']['roles_mode'] = 'all';
        return $this;
    }

    /**
     * Include the initiator and optionally preapprove them.
     *
     * @param bool $include Whether to include initiator as potential signer
     * @param bool $preApprove Whether to count initiator immediately
     * @return static
     */
    public function includeInitiator(bool $include = true, bool $preApprove = true): static
    {
        $this->current['meta']['include_initiator'] = $include;
        $this->current['meta']['preapprove_initiator'] = $preApprove;
        return $this;
    }

    /**
     * Require overlap with at least one initiator permission.
     *
     * @param bool $enable Enable/disable constraint
     * @return static
     */
    public function samePermissionAsInitiator(bool $enable = true): static
    {
        $this->current['signers']['same_permission_as_initiator'] = $enable;
        return $this;
    }

    /**
     * Require overlap with at least one initiator role.
     *
     * @param bool $enable Enable/disable constraint
     * @return static
     */
    public function sameRoleAsInitiator(bool $enable = true): static
    {
        $this->current['signers']['same_role_as_initiator'] = $enable;
        return $this;
    }

    /**
     * Finalize the current step and append it to the flow.
     *
     * @param int|null $threshold Minimum required approvals for this step
     * @param string|null $name Optional step display name
     * @param array $meta Additional step metadata
     * @return static
     */
    public function toStep(?int $threshold = 1, ?string $name = null, array $meta = []): static
    {
        $step = $this->current;
        $step['threshold'] = (int) ($threshold ?? 1);
        $step['name'] = $name ?? ('Step '.(count($this->steps) + 1));
        $step['meta'] = array_merge($step['meta'] ?? [], $meta);

        $this->steps[] = [
            'name' => $step['name'],
            'threshold' => $step['threshold'],
            'signers' => $step['signers'],
            'meta' => $step['meta'],
        ];

        // Reset current keeping previous signer config as starting point
        $this->current['name'] = null;
        $this->current['threshold'] = 1;
        $this->current['meta'] = [
            'include_initiator' => false,
            'preapprove_initiator' => true,
        ];
        return $this;
    }

    /**
     * Add a normalized step array directly.
     *
     * @param array $step Normalized step array
     * @return static
     */
    public function addStep(array $step): static
    {
        $this->steps[] = [
            'name' => (string) ($step['name'] ?? ('Step '.(count($this->steps)+1))),
            'threshold' => (int) ($step['threshold'] ?? 1),
            'signers' => (array) ($step['signers'] ?? []),
            'meta' => (array) ($step['meta'] ?? []),
        ];
        return $this;
    }

    /**
     * Build and return the configured flow.
     *
     * @return array<int,array<string,mixed>> Normalized flow definition
     */
    public function build(): array
    {
        // If caller never called toStep but configured signers, create a single step
        if (empty($this->steps) && ($this->current['signers']['permissions'] || $this->current['signers']['roles'] || $this->current['signers']['guard'])) {
            $this->toStep();
        }
        return $this->steps;
    }
}
