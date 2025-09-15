<?php

namespace OVAC\Guardrails\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use OVAC\Guardrails\Concerns\ActorGuarded;
use OVAC\Guardrails\Services\Flow;

class Post extends Model
{
    use ActorGuarded;

    protected $table = 'posts';
    protected $guarded = [];
    protected $casts = [
        'published' => 'boolean',
    ];

    public function humanGuardAttributes(): array
    {
        return ['published'];
    }

    public function actorApprovalFlow(array $dirty, string $event): array
    {
        return [
            Flow::make()->anyOfPermissions(['content.publish'])->toStep(1, 'Editorial')->build(),
        ];
    }
}
