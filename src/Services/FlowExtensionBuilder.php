<?php

namespace OVAC\Guardrails\Services;

use OVAC\Guardrails\Contracts\FlowExtender;

/**
 * Fluent builder for multi-step Guardrails approval flows.
 *
 * Examples:
 * - Any-of permissions, count initiator:
 *   FlowExtensionBuilder::make()
 *     ->permissionsAny(['orders.manage','orders.escalate'])
 *     ->includeInitiator(true, true)
 *     ->toStep(2, 'Ops Review')
 *     ->build();
 *
 * - Any-of roles:
 *   FlowExtensionBuilder::make()->rolesAny(['ops_manager','finance_manager'])->toStep(1, 'Mgmt')->build();
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

    /** Create a new builder instance. */
    public static function make(): static
    {
        return new static();
    }

    /** Set the auth guard to evaluate against. */
    public function guard(string $guard): static
    {
        $this->current['signers']['guard'] = $guard;
        return $this;
    }

    /** Append permission(s). All-of semantics by default. */
    public function permissions(array|string $permissions): static
    {
        $perms = is_array($permissions) ? $permissions : [$permissions];
        $this->current['signers']['permissions'] = array_values(array_unique(array_merge($this->current['signers']['permissions'], $perms)));
        return $this;
    }

    /** Replace permission set entirely. */
    public function setPermissions(array|string $permissions): static
    {
        $this->current['signers']['permissions'] = is_array($permissions) ? array_values($permissions) : [$permissions];
        return $this;
    }

    public function permissionsAny(array|string $permissions): static
    {
        $this->permissions($permissions);
        $this->current['signers']['permissions_mode'] = 'any';
        return $this;
    }

    public function requireAnyPermissions(): static
    {
        $this->current['signers']['permissions_mode'] = 'any';
        return $this;
    }

    public function requireAllPermissions(): static
    {
        $this->current['signers']['permissions_mode'] = 'all';
        return $this;
    }

    /** Append role(s). All-of semantics by default. */
    public function roles(array|string $roles): static
    {
        $rs = is_array($roles) ? $roles : [$roles];
        $this->current['signers']['roles'] = array_values(array_unique(array_merge($this->current['signers']['roles'], $rs)));
        return $this;
    }

    /** Replace role set entirely. */
    public function setRoles(array|string $roles): static
    {
        $this->current['signers']['roles'] = is_array($roles) ? array_values($roles) : [$roles];
        return $this;
    }

    public function rolesAny(array|string $roles): static
    {
        $this->roles($roles);
        $this->current['signers']['roles_mode'] = 'any';
        return $this;
    }

    public function requireAnyRoles(): static
    {
        $this->current['signers']['roles_mode'] = 'any';
        return $this;
    }

    public function requireAllRoles(): static
    {
        $this->current['signers']['roles_mode'] = 'all';
        return $this;
    }

    public function includeInitiator(bool $include = true, bool $preApprove = true): static
    {
        $this->current['meta']['include_initiator'] = $include;
        $this->current['meta']['preapprove_initiator'] = $preApprove;
        return $this;
    }

    public function samePermissionAsInitiator(bool $enable = true): static
    {
        $this->current['signers']['same_permission_as_initiator'] = $enable;
        return $this;
    }

    public function sameRoleAsInitiator(bool $enable = true): static
    {
        $this->current['signers']['same_role_as_initiator'] = $enable;
        return $this;
    }

    /** Finalize the current step and append it to the flow. */
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

    /** Add a normalized step array directly. */
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

    /** Build and return the configured flow. */
    public function build(): array
    {
        // If caller never called toStep but configured signers, create a single step
        if (empty($this->steps) && ($this->current['signers']['permissions'] || $this->current['signers']['roles'] || $this->current['signers']['guard'])) {
            $this->toStep();
        }
        return $this->steps;
    }
}
