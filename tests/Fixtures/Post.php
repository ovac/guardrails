<?php

namespace OVAC\Guardrails\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use OVAC\Guardrails\Concerns\HumanGuarded;
use OVAC\Guardrails\Services\FlowExtensionBuilder as Flow;

class Post extends Model
{
    use HumanGuarded;

    protected $table = 'posts';
    protected $guarded = [];
    protected $casts = [
        'published' => 'boolean',
    ];

    public function humanGuardAttributes(): array
    {
        return ['published'];
    }

    public function humanApprovalFlow(array $dirty, string $event): array
    {
        return [
            Flow::make()->anyOfPermissions(['content.publish'])->toStep(1, 'Editorial')->build(),
        ];
    }
}
