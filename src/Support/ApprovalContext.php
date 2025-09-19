<?php

namespace OVAC\Guardrails\Support;

use OVAC\Guardrails\Contracts\FlowExtender;

/**
 * Fluent helper for attaching runtime context (description/meta/options) to approvals.
 */
class ApprovalContext
{
    protected object $model;

    protected ?string $description = null;

    protected ?array $meta = null;

    protected array $options = [];

    /**
     * Create a new approval context instance.
     *
     * @param  object  $model  Consuming model instance for future extensions.
     */
    public function __construct(object $model)
    {
        $this->model = $model;
    }

    /**
     * Set the human-readable description for the pending approval capture.
     *
     * @param  string|null  $description  Human-readable summary string.
     * @return self
     */
    public function description(?string $description): self
    {
        $this->description = $description !== null ? trim($description) : null;
        $this->options['description'] = $this->description ?: null;
        return $this;
    }

    /**
     * Replace the meta payload for the pending approval capture.
     *
     * @param  array<string, mixed>|null  $meta  Meta payload to persist with the request.
     * @return self
     */
    public function meta(?array $meta): self
    {
        $this->meta = $meta === null ? null : $this->normalizeMeta($meta);
        $this->options['meta'] = $this->meta;
        return $this;
    }

    /**
     * Merge additional meta data into the pending payload.
     *
     * @param  array<string, mixed>  $meta  Meta attributes to merge into the payload.
     * @return self
     */
    public function mergeMeta(array $meta): self
    {
        $current = $this->meta ?? [];
        $this->meta = $this->normalizeMeta(array_replace_recursive($current, $meta));
        $this->options['meta'] = $this->meta;
        return $this;
    }

    /**
     * Restrict guard evaluation to specific attributes.
     *
     * @param  array<int, string>|string  $attributes  Attribute keys that must be guarded.
     * @return self
     */
    public function only(array|string $attributes): self
    {
        $values = array_values(array_unique((array) $attributes));
        $this->options['only'] = $values;
        return $this;
    }

    /**
     * Exclude attributes from guard evaluation.
     *
     * @param  array<int, string>|string  $attributes  Attribute keys that should bypass guarding.
     * @return self
     */
    public function except(array|string $attributes): self
    {
        $values = array_values(array_unique((array) $attributes));
        $this->options['except'] = $values;
        return $this;
    }

    /**
     * Override the event name passed to the approval capture.
     *
     * @param  string  $event  Lifecycle event label (creating/updating/custom).
     * @return self
     */
    public function event(string $event): self
    {
        $this->options['event'] = $event;
        return $this;
    }

    /**
     * Override the approval flow to use for this capture.
     *
     * @param  array<int, array<string, mixed>>  $flow  Normalized flow definition.
     * @return self
     */
    public function flow(array $flow): self
    {
        $this->options['flow'] = $flow;
        return $this;
    }

    /**
     * Provide a FlowExtender to build the approval flow for this capture.
     *
     * @param  FlowExtender  $extender  Builder responsible for generating the flow definition.
     * @return self
     */
    public function extender(FlowExtender $extender): self
    {
        $this->options['flow'] = $extender->build();
        return $this;
    }

    /**
     * Get the normalized options array consumed by the approval service.
     *
     * @return array<string, mixed>
     */
    public function options(): array
    {
        $options = $this->options;
        if ($this->description !== null && $this->description !== '') {
            $options['description'] = $this->description;
        }
        if ($this->meta !== null) {
            $options['meta'] = $this->meta;
        }

        return array_filter(
            $options,
            static fn ($value) => $value !== null && $value !== [] && $value !== ''
        );
    }

    /**
     * Clear any stored context.
     *
     * @return void
     */
    public function clear(): void
    {
        $this->description = null;
        $this->meta = null;
        $this->options = [];
    }

    /**
     * Ensure meta payload is array-only (recursively removing objects).
     *
     * @param  array<string, mixed>  $meta  Meta payload provided by the caller.
     * @return array<string, mixed>
     */
    protected function normalizeMeta(array $meta): array
    {
        foreach ($meta as $key => $value) {
            if (is_array($value)) {
                $meta[$key] = $this->normalizeMeta($value);
                continue;
            }

            if (is_object($value)) {
                $meta[$key] = method_exists($value, 'toArray') ? $value->toArray() : (string) $value;
            }
        }

        return $meta;
    }
}
