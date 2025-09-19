<?php

namespace OVAC\Guardrails\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use OVAC\Guardrails\Concerns\Guardrail;
use OVAC\Guardrails\Services\Flow;

/**
 * Test fixture model representing a post guarded by the Guardrail concern.
 */
class Post extends Model
{
    use Guardrail;

    protected $table = 'posts';

    protected $guarded = [];

    protected $casts = [
        'published' => 'boolean',
    ];

    /**
     * Attributes that should trigger Guardrails staging when mutated.
     *
     * @return array<int, string>
     */
    public function guardrailAttributes(): array
    {
        return ['published'];
    }

    /**
     * Define the approval flow used for the post fixture during tests.
     *
     * @param  array<string, mixed>  $dirty
     * @param  string  $event
     * @return array<int, array<string, mixed>>
     */
    public function guardrailApprovalFlow(array $dirty, string $event): array
    {
        return Flow::make()
            ->anyOfPermissions(['content.publish'])
            ->anyOfRoles(['editor'])
            ->signedBy(1, 'Editorial')
            ->build();
    }
}
