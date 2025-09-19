<?php

namespace OVAC\Guardrails\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use OVAC\Guardrails\Concerns\Guardrail;
use OVAC\Guardrails\Services\Flow;

/**
 * Test fixture model demonstrating pre-approval of initiator signatures.
 */
class PrePost extends Model
{
    use Guardrail;

    protected $table = 'posts';

    protected $guarded = [];

    protected $casts = [
        'published' => 'boolean',
    ];

    /**
     * Attributes triggering Guardrails staging for this test fixture.
     *
     * @return array<int, string>
     */
    public function guardrailAttributes(): array
    {
        return ['published'];
    }

    /**
     * Define the approval flow used for the pre-approval test scenario.
     *
     * @param  array<string, mixed>  $dirty
     * @param  string  $event
     * @return array<int, array<string, mixed>>
     */
    public function guardrailApprovalFlow(array $dirty, string $event): array
    {
        return Flow::make()
            ->anyOfPermissions(['content.publish'])
            ->includeInitiator(true, true)
            ->signedBy(1, 'AutoCount')
            ->build();
    }
}
