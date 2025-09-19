<?php

namespace OVAC\Guardrails\Services;

use OVAC\Guardrails\Contracts\FlowExtender;

/**
 * Fluent builder for multi-step Guardrails approval flows.
 *
 * Examples:
 * - Any-of permissions, count initiator:
 *   FlowBuilder::make()
 *     ->anyOfPermissions(['orders.manage','orders.escalate'])
 *     ->includeInitiator(true, true)
 *     ->signedBy(2, 'Ops Review')
 *     ->build();
 *
 * - Any-of roles:
 *   FlowBuilder::make()->anyOfRoles(['ops_manager','finance_manager'])->signedBy(1, 'Mgmt')->build();
 */
class FlowBuilder implements FlowExtender
{
    protected array $steps = [];

    protected array $current = [
        'name' => null,
        'threshold' => 1,
        'signers' => [
            'guard' => null,
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
            'rejection_min' => null,
            'rejection_max' => null,
        ],
    ];

    /**
     * Create a new builder instance.
     *
     * @return static
     */
    /**
     * @return static
     */
    public static function make(): static
    {
        /** @phpstan-suppress unsafe-new-static */
        /** @var static $instance */
        $instance = new static();
        $defaultGuard = static::defaultGuard();
        if ($defaultGuard !== null) {
            $instance->guard($defaultGuard);
        }

        return $instance;
    }

    /**
     * Resolve the default guard that should be applied to new flow builders.
     *
     * @return string|null Guard name or null when configuration is unavailable
     */
    protected static function defaultGuard(): ?string
    {
        if (!function_exists('config')) {
            return 'web';
        }

        $configured = config('guardrails.auth.guard');
        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        $fallback = config('auth.defaults.guard');
        if (is_string($fallback) && $fallback !== '') {
            return $fallback;
        }

        return 'web';
    }

    /**
     * Set the auth guard to evaluate against when checking signers.
     *
     * @param  string  $guard  Guard name defined in auth.guards.
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
     * @param  array<int, string>|string  $permissions  Permission names.
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
     * @param  array<int, string>|string  $permissions  Permission names.
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
     * @param  array<int, string>|string  $permissions  Permission names.
     * @return static
     */
    public function anyOfPermissions(array|string $permissions): static
    {
        $this->permissions($permissions);
        $this->current['signers']['permissions_mode'] = 'any';
        return $this;
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
     * @param  array<int, string>|string  $roles  Role names.
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
     * @param  array<int, string>|string  $roles  Role names.
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
     * @param  array<int, string>|string  $roles  Role names.
     * @return static
     */
    public function anyOfRoles(array|string $roles): static
    {
        $this->roles($roles);
        $this->current['signers']['roles_mode'] = 'any';
        return $this;
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
     * @param  bool  $include     Whether to include initiator as potential signer
     * @param  bool  $preApprove  Whether to count initiator immediately
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
     * @param  bool  $enable  Enable/disable constraint.
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
     * @param  bool  $enable  Enable/disable constraint.
     * @return static
     */
    public function sameRoleAsInitiator(bool $enable = true): static
    {
        $this->current['signers']['same_role_as_initiator'] = $enable;
        return $this;
    }

    /**
     * Configure rejection thresholds for the current step.
     *
     * When unset, Guardrails falls back to a simple majority of the approval threshold.
     *
     * @param  int|null  $minimum  Minimum rejection signatures required to fail the step.
     * @param  int|null  $maximum  Optional hard stop; defaults to the minimum when omitted.
     * @return static
     */
    public function rejectionThreshold(?int $minimum, ?int $maximum = null): static
    {
        if ($minimum !== null && $minimum < 1) {
            throw new \InvalidArgumentException('Rejection minimum must be null or a positive integer.');
        }

        if ($maximum !== null && $maximum < 1) {
            throw new \InvalidArgumentException('Rejection maximum must be null or a positive integer.');
        }

        if ($minimum !== null && $maximum !== null && $maximum < $minimum) {
            throw new \InvalidArgumentException('Rejection maximum cannot be smaller than the minimum.');
        }

        $this->current['meta']['rejection_min'] = $minimum;
        $this->current['meta']['rejection_max'] = $maximum;

        return $this;
    }

    /**
     * Set the minimum number of rejection signatures required to fail the step.
     *
     * @param  int  $minimum
     * @return static
     */
    public function minRejections(int $minimum): static
    {
        return $this->rejectionThreshold($minimum, $this->current['meta']['rejection_max']);
    }

    /**
     * Set the maximum number of rejection signatures that can be recorded before the step fails.
     *
     * @param  int|null  $maximum
     * @return static
     */
    public function maxRejections(?int $maximum): static
    {
        return $this->rejectionThreshold($this->current['meta']['rejection_min'], $maximum);
    }

    /**
     * Finalize the current step and append it to the flow.
     *
     * @param  int|null  $threshold  Minimum required approvals for this step
     * @param  string|null  $name     Optional step display name
     * @param  array<string, mixed>  $meta  Additional step metadata
     * @return static
     */
    public function signedBy(?int $threshold = 1, ?string $name = null, array $meta = []): static
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

        // Reset current signers/meta for next step while keeping guard default
        $guard = $this->current['signers']['guard'] ?? null;

        $this->current['name'] = null;
        $this->current['threshold'] = 1;
        $this->current['signers'] = [
            'guard' => $guard,
            'permissions' => [],
            'permissions_mode' => 'all',
            'roles' => [],
            'roles_mode' => 'all',
            'same_permission_as_initiator' => false,
            'same_role_as_initiator' => false,
        ];
        $this->current['meta'] = [
            'include_initiator' => false,
            'preapprove_initiator' => true,
            'rejection_min' => null,
            'rejection_max' => null,
        ];
        return $this;
    }

    /**
     * Add a normalized step array directly.
     *
     * @param  array<string, mixed>  $step  Normalized step array
     * @return static
     */
    public function addStep(array $step): static
    {
        $this->steps[] = [
            'name' => (string) ($step['name'] ?? ('Step '.(count($this->steps) + 1))),
            'threshold' => (int) ($step['threshold'] ?? 1),
            'signers' => (array) ($step['signers'] ?? []),
            'meta' => (array) ($step['meta'] ?? []),
        ];
        return $this;
    }

    /**
     * Build and return the configured flow.
     *
     * @return array<int, array<string, mixed>> Normalized flow definition
     */
    public function build(): array
    {
        // If caller never called signedBy but configured signers, create a single step
        if (empty($this->steps) && ($this->current['signers']['permissions'] || $this->current['signers']['roles'] || $this->current['signers']['guard'])) {
            $this->signedBy();
        }
        return $this->steps;
    }
}
